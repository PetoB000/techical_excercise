<?php

declare(strict_types=1);

namespace AcmeLearn\Importer\Tests;

use AcmeLearn\Importer\Api\Schema;
use GraphQL\GraphQL;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the GraphQL API layer against an in-memory store.
 */
final class GraphqlImportTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec((string) file_get_contents(__DIR__ . '/../schema.sql'));
    }

    /**
     * Execute a GraphQL operation and return the result as an array.
     *
     * @param array<string, mixed> $variables
     *
     * @return array<string, mixed>
     */
    private function execute(string $query, array $variables = []): array
    {
        $result = GraphQL::executeQuery(
            schema: Schema::build(),
            source: $query,
            contextValue: ['pdo' => $this->pdo],
            variableValues: $variables,
            fieldResolver: Schema::fieldResolver(),
        );

        return $result->toArray();
    }

    private function importCsv(string $csv): array
    {
        return $this->execute(
            'mutation($f: String!, $c: String!) {
                importCsv(filename: $f, contentBase64: $c) {
                    rowsRead created updated skipped skippedRows { line errors }
                }
            }',
            ['f' => 'export.csv', 'c' => base64_encode($csv)],
        );
    }

    public function testImportCsvMutationReturnsSummaryForValidRow(): void
    {
        $result = $this->importCsv(
            "hr_id,first_name,last_name,email,department,active\n"
            . "E1,Alice,Adams,alice@example.com,Eng,1\n"
        );

        self::assertArrayNotHasKey('errors', $result);
        self::assertSame(
            ['rowsRead' => 1, 'created' => 1, 'updated' => 0, 'skipped' => 0, 'skippedRows' => []],
            $result['data']['importCsv'],
        );
    }

    public function testImportCsvReportsSkippedRowsWithReasons(): void
    {
        $result = $this->importCsv(
            "hr_id,first_name,last_name,email,department,active\n"
            . "E2,Bob,Brown,not-an-email,Sales,1\n"
        );

        $summary = $result['data']['importCsv'];
        self::assertSame(0, $summary['created']);
        self::assertSame(1, $summary['skipped']);
        self::assertSame(2, $summary['skippedRows'][0]['line']);
        self::assertContains('invalid email', $summary['skippedRows'][0]['errors']);
    }

    public function testUsersQueryReturnsImportedUserInCamelCase(): void
    {
        $this->importCsv(
            "hr_id,first_name,last_name,email,department,active\n"
            . "E1,Carol,Clarke,CAROL.CLARKE@example.com,Marketing,0\n"
        );

        $result = $this->execute('{ users { id hrId firstName lastName email department isActive } }');

        self::assertArrayNotHasKey('errors', $result);
        self::assertSame(
            [
                'id'         => 1,
                'hrId'       => 'E1',
                'firstName'  => 'Carol',
                'lastName'   => 'Clarke',
                'email'      => 'carol.clarke@example.com',
                'department' => 'Marketing',
                'isActive'   => false,
            ],
            $result['data']['users'][0],
        );
    }

    public function testReimportingAnExistingHrIdUpdatesInPlace(): void
    {
        $first = $this->importCsv(
            "hr_id,first_name,last_name,email,department,active\n"
            . "E1,Alice,Adams,alice@example.com,Eng,1\n"
        );
        self::assertArrayNotHasKey('errors', $first);
        self::assertSame(1, $first['data']['importCsv']['created']);
        self::assertSame(0, $first['data']['importCsv']['updated']);

        $second = $this->importCsv(
            "hr_id,first_name,last_name,email,department,active\n"
            . "E1,Alice,Adams-Updated,alice@example.com,Engineering,1\n"
        );
        self::assertArrayNotHasKey('errors', $second);
        self::assertSame(0, $second['data']['importCsv']['created']);
        self::assertSame(1, $second['data']['importCsv']['updated']);
    }

    public function testUpdateUserMutationReturnsUpdatedUser(): void
    {
        $this->importCsv(
            "hr_id,first_name,last_name,email,department,active\n"
            . "E1,Alice,Adams,alice@example.com,Engineering,1\n"
        );

        $userId = $this->pdo->query('SELECT id FROM users WHERE hr_id = "E1"')->fetchColumn();

        $result = $this->execute(
            'mutation($id: Int!, $input: UpdateUserInput!) {
                updateUser(id: $id, input: $input) {
                    id firstName lastName department isActive
                }
            }',
            ['id' => (int) $userId, 'input' => ['firstName' => 'Alicia', 'department' => 'Leadership', 'isActive' => false]],
        );

        self::assertArrayNotHasKey('errors', $result);
        $user = $result['data']['updateUser'];
        self::assertSame('Alicia', $user['firstName']);
        self::assertSame('Adams', $user['lastName'], 'Unchanged fields should be preserved.');
        self::assertSame('Leadership', $user['department']);
        self::assertFalse($user['isActive']);
    }
}
