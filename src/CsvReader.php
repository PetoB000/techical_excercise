<?php

declare(strict_types=1);

namespace AcmeLearn\Importer;

/**
 * Reads a CSV file as a lazy generator of associative rows keyed by the header row.
 */
final class CsvReader
{
    /**
     * @return \Generator<int, array<string, string>>
     */
    public function read(string $path): \Generator
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new \RuntimeException("Unable to open file: {$path}");
        }

        $header = fgetcsv($handle, escape: '');
        if ($header === false) {
            fclose($handle);

            return;
        }

        while (($line = fgetcsv($handle, escape: '')) !== false) {
            // Defensively pad/truncate the line to the header width so
            // array_combine() never fails on a ragged row.
            $line = array_pad(array_slice($line, 0, count($header)), count($header), '');
            yield array_combine($header, $line);
        }

        fclose($handle);
    }
}
