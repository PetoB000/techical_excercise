<?php

declare(strict_types=1);

namespace AcmeLearn\Importer\Api\Resolvers\Mutation;

use AcmeLearn\Importer\Api\Resolvers\mutation_resolver;
use AcmeLearn\Importer\CsvReader;
use AcmeLearn\Importer\ImportRunner;
use AcmeLearn\Importer\ImportSummary;
use AcmeLearn\Importer\UserRepository;
use AcmeLearn\Importer\UserValidator;
use PDO;
use RuntimeException;

/**
 * Resolves the `importCsv` mutation.
 */
final class ImportCsv extends mutation_resolver
{
    /**
     * Import a base64-encoded CSV. The decoded content is written to a temp file
     * and handed to the existing import pipeline unchanged.
     *
     * @param array{filename: string, contentBase64: string} $args
     *
     * @return array<string, int|array<int, array{line: int, errors: string[]}>>
     */
    public function resolve(PDO $pdo, array $args): array
    {
        $contents = base64_decode($args['contentBase64'], true);
        if ($contents === false) {
            throw new RuntimeException('contentBase64 is not valid base64.');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'import');
        file_put_contents($tmp, $contents);

        try {
            $runner = new ImportRunner(
                new CsvReader(),
                new UserValidator(),
                new UserRepository($pdo),
            );

            $summary = $runner->run($tmp);
        } finally {
            unlink($tmp);
        }

        return $this->summaryToGraphql($summary);
    }

    /**
     * @return array<string, int|array<int, array{line: int, errors: string[]}>>
     */
    private function summaryToGraphql(ImportSummary $summary): array
    {
        return [
            'rowsRead'    => $summary->rowsRead(),
            'created'     => $summary->imported(),
            'updated'     => $summary->updated(),
            'skipped'     => $summary->skippedCount(),
            'skippedRows' => $summary->skippedRows(),
        ];
    }
}
