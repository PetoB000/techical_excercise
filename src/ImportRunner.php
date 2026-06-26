<?php

declare(strict_types=1);

namespace AcmeLearn\Importer;

/**
 * Orchestrates a CSV import: read, normalise, validate, persist and summarise.
 */
final class ImportRunner
{
    public function __construct(
        private readonly CsvReader $reader,
        private readonly UserValidator $validator,
        private readonly UserRepository $repository,
    ) {
    }

    public function run(string $csvPath): ImportSummary
    {
        $summary = new ImportSummary();

        $existingHrIds = array_flip($this->repository->findAllHrIds());

        $this->repository->beginTransaction();

        try {
            foreach ($this->reader->read($csvPath) as $index => $row) {
                $summary->incrementRowsRead();
                $line = $index + 2; // +1 for the header, +1 to make it 1-based

                // Normalise: trim whitespace and lower-case the email so duplicate
                // addresses are caught consistently regardless of how HR typed them.
                $row['first_name'] = trim($row['first_name'] ?? '');
                $row['last_name']  = trim($row['last_name'] ?? '');
                $row['email']      = strtolower(trim($row['email'] ?? ''));
                $row['is_active']  = (($row['active'] ?? '1') === '1') ? 1 : 0;

                $errors = $this->validator->validate($row);
                if ($errors !== []) {
                    $summary->addSkipped($line, $errors);
                    continue;
                }

                $hrId  = (string) $row['hr_id'];
                $isNew = !isset($existingHrIds[$hrId]);

                $this->repository->upsert($row);

                if ($isNew) {
                    $existingHrIds[$hrId] = true; // track within this run
                    $summary->addImported();
                } else {
                    $summary->addUpdated();
                }
            }

            $this->repository->commit();
        } catch (\Throwable $e) {
            $this->repository->rollBack();
            throw $e;
        }

        return $summary;
    }
}
