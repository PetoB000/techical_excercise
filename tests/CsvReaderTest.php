<?php

declare(strict_types=1);

namespace AcmeLearn\Importer\Tests;

use AcmeLearn\Importer\CsvReader;
use PHPUnit\Framework\TestCase;

final class CsvReaderTest extends TestCase
{
    private CsvReader $reader;

    protected function setUp(): void
    {
        $this->reader = new CsvReader();
    }

    private function writeCsv(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, $contents);

        return $path;
    }

    public function testReturnsAGenerator(): void
    {
        $path = $this->writeCsv("hr_id,first_name\nE1,Alice\n");

        $result = $this->reader->read($path);

        self::assertInstanceOf(\Generator::class, $result);
    }

    public function testYieldsCorrectRowsForWellFormedCsv(): void
    {
        $path = $this->writeCsv(
            "hr_id,first_name,last_name,email\n"
            . "E1,Alice,Adams,alice@example.com\n"
            . "E2,Bob,Brown,bob@example.com\n"
        );

        $rows = iterator_to_array($this->reader->read($path));

        self::assertCount(2, $rows);
        self::assertSame(['hr_id' => 'E1', 'first_name' => 'Alice', 'last_name' => 'Adams', 'email' => 'alice@example.com'], $rows[0]);
        self::assertSame(['hr_id' => 'E2', 'first_name' => 'Bob',   'last_name' => 'Brown', 'email' => 'bob@example.com'],   $rows[1]);
    }

    public function testEmptyFileReturnsEmptyGenerator(): void
    {
        $path = $this->writeCsv('');

        $rows = iterator_to_array($this->reader->read($path));

        self::assertSame([], $rows);
    }

    public function testHeaderOnlyFileReturnsEmptyGenerator(): void
    {
        $path = $this->writeCsv("hr_id,first_name,last_name\n");

        $rows = iterator_to_array($this->reader->read($path));

        self::assertSame([], $rows);
    }

    public function testRaggedRowIsPaddedToHeaderWidth(): void
    {
        $path = $this->writeCsv("hr_id,first_name,department\nE1,Alice\n");

        $rows = iterator_to_array($this->reader->read($path));

        self::assertSame('', $rows[0]['department'], 'Missing columns should be padded with empty strings.');
    }

    public function testExtraColumnsAreTruncatedToHeaderWidth(): void
    {
        $path = $this->writeCsv("hr_id,first_name\nE1,Alice,unexpected-extra-column\n");

        $rows = iterator_to_array($this->reader->read($path));

        self::assertCount(2, $rows[0], 'Extra columns beyond the header should be dropped.');
        self::assertArrayNotHasKey(2, $rows[0]);
    }
}
