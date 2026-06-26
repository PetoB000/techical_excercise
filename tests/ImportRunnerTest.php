<?php

declare(strict_types=1);

namespace AcmeLearn\Importer\Tests;

use AcmeLearn\Importer\CsvReader;
use AcmeLearn\Importer\ImportRunner;
use AcmeLearn\Importer\UserRepository;
use AcmeLearn\Importer\UserValidator;
use PDO;
use PHPUnit\Framework\TestCase;

final class ImportRunnerTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec((string) file_get_contents(__DIR__ . '/../schema.sql'));
    }

    private function runner(): ImportRunner
    {
        return new ImportRunner(
            new CsvReader(),
            new UserValidator(),
            new UserRepository($this->pdo),
        );
    }

    private function writeCsv(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, $contents);

        return $path;
    }

    public function testEveryValidRowIsPersisted(): void
    {
        $csv = $this->writeCsv(
            "hr_id,first_name,last_name,email,department,active\n"
            . "E1,Alice,Adams,alice@example.com,Eng,1\n"
            . "E2,Bob,Brown,bob@example.com,Sales,1\n"
            . "E3,Carol,Clarke,carol@example.com,Marketing,1\n"
        );

        $summary = $this->runner()->run($csv);

        self::assertSame(3, $summary->imported());
        self::assertSame(0, $summary->skippedCount());

        $repository = new UserRepository($this->pdo);
        self::assertSame(3, $repository->count());
        self::assertTrue($repository->existsByHrId('E3'), 'The last row should be imported.');
    }

    public function testInvalidRowsAreSkippedWithReasons(): void
    {
        $csv = $this->writeCsv(
            "hr_id,first_name,last_name,email,department,active\n"
            . "E1,Alice,Adams,alice@example.com,Eng,1\n"
            . "E2,David,,david@example.com,Eng,1\n"
            . "E3,Eve,Evans,not-an-email,Finance,1\n"
        );

        $summary = $this->runner()->run($csv);

        self::assertSame(1, $summary->imported());
        self::assertSame(2, $summary->skippedCount());
    }

    public function testReimportUpdatesExistingUserInPlace(): void
    {
        $runner = $this->runner();
        $repository = new UserRepository($this->pdo);

        $first = $this->writeCsv(
            "hr_id,first_name,last_name,email,department,active\n"
            . "E1,Alice,Adams,alice@example.com,Eng,1\n"
        );
        $summary = $runner->run($first);
        self::assertSame(1, $summary->imported());
        self::assertSame(0, $summary->updated());

        $second = $this->writeCsv(
            "hr_id,first_name,last_name,email,department,active\n"
            . "E1,Alice,Adams-Updated,alice@example.com,Engineering,1\n"
        );
        $summary = $runner->run($second);
        self::assertSame(0, $summary->imported());
        self::assertSame(1, $summary->updated());

        self::assertSame(1, $repository->count(), 'No duplicate row should be created.');
        $row = $this->pdo->query("SELECT last_name, department FROM users WHERE hr_id = 'E1'")->fetch(\PDO::FETCH_ASSOC);
        self::assertSame('Adams-Updated', $row['last_name']);
        self::assertSame('Engineering', $row['department']);
    }

    public function testEmailIsNormalised(): void
    {
        $csv = $this->writeCsv(
            "hr_id,first_name,last_name,email,department,active\n"
            . "E1,Bob,Brown, BOB.BROWN@EXAMPLE.COM ,Sales,1\n"
        );

        $this->runner()->run($csv);

        $stored = $this->pdo->query("SELECT email FROM users WHERE hr_id = 'E1'")->fetchColumn();
        self::assertSame('bob.brown@example.com', $stored);
    }
}
