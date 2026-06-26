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
        $rows = $this->reader->read($csvPath);
        $summary = new ImportSummary(count($rows));

        // Normalise every row before validation: trim surrounding whitespace
        // and lower-case the email so duplicate addresses are caught
        // consistently regardless of how HR typed them.
        foreach ($rows as &$row) {
            $row['first_name'] = trim($row['first_name'] ?? '');
            $row['last_name']  = trim($row['last_name'] ?? '');
            $row['email']      = strtolower(trim($row['email'] ?? ''));
            $row['is_active']  = (($row['active'] ?? '1') === '1') ? 1 : 0;
        }
        unset($row); // break the reference left by the foreach above

        foreach ($rows as $index => $row) {
            $line = $index + 2; // +1 for the header, +1 to make it 1-based

            $errors = $this->validator->validate($row);
            if ($errors !== []) {
                $summary->addSkipped($line, $errors);
                continue;
            }

            $this->repository->insert($row);
            $summary->addImported();
        }

        return $summary;
    }
}
