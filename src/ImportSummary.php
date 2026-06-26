<?php

declare(strict_types=1);

namespace AcmeLearn\Importer;

/**
 * Collects the results of an import run for reporting.
 */
final class ImportSummary
{
    private int $rowsRead = 0;
    private int $imported = 0;
    private int $updated = 0;

    /** @var array<int, string[]> Map of CSV line number => validation errors. */
    private array $skipped = [];

    public function incrementRowsRead(): void
    {
        $this->rowsRead++;
    }

    public function addImported(): void
    {
        $this->imported++;
    }

    public function addUpdated(): void
    {
        $this->updated++;
    }

    /**
     * @param string[] $errors
     */
    public function addSkipped(int $line, array $errors): void
    {
        $this->skipped[$line] = $errors;
    }

    public function imported(): int
    {
        return $this->imported;
    }

    public function updated(): int
    {
        return $this->updated;
    }

    public function skippedCount(): int
    {
        return count($this->skipped);
    }

    public function rowsRead(): int
    {
        return $this->rowsRead;
    }

    /**
     * @return array<int, array{line: int, errors: string[]}>
     */
    public function skippedRows(): array
    {
        $rows = [];
        foreach ($this->skipped as $line => $errors) {
            $rows[] = ['line' => $line, 'errors' => $errors];
        }

        return $rows;
    }

    public function hasErrors(): bool
    {
        return $this->skipped !== [];
    }

    public function format(): string
    {
        $lines = [];
        $lines[] = 'Import complete.';
        $lines[] = sprintf('  Rows read: %d', $this->totalRows);
        $lines[] = sprintf('  Created:   %d', $this->imported);
        $lines[] = sprintf('  Updated:   %d', $this->updated);
        $lines[] = sprintf('  Skipped:   %d', count($this->skipped));

        if ($this->skipped !== []) {
            $lines[] = '';
            $lines[] = 'Skipped rows:';
            foreach ($this->skipped as $line => $errors) {
                $lines[] = sprintf('  line %d: %s', $line, implode(', ', $errors));
            }
        }

        return implode("\n", $lines) . "\n";
    }
}
