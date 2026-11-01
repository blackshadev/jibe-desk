<?php

declare(strict_types=1);

namespace App\Infrastructure\Members\Import;

use App\Domain\Members\Import\LegacyCsvImportException;
use DateTimeImmutable;

/**
 * Reads a UTF-8 comma-separated legacy export into header-keyed rows.
 */
final readonly class LegacyCsvReader
{
    /**
     * @param list<string> $requiredHeaders
     * @return list<SpreadsheetImportRow>
     */
    public function read(string $path, array $requiredHeaders): array
    {
        $handle = $this->open($path);

        try {
            $headers = $this->headers($handle, $requiredHeaders);
            $this->assertRequiredHeaders($headers, $requiredHeaders);

            return $this->rows($handle, $headers);
        } finally {
            fclose($handle);
        }
    }

    public function text(SpreadsheetImportRow $row, string $column): string
    {
        $value = $row->values[$column] ?? null;

        return trim((string) $value);
    }

    public function requiredText(SpreadsheetImportRow $row, string $column, ?string $lidnr = null): string
    {
        $value = $this->text($row, $column);

        if ($value === '') {
            throw LegacyCsvImportException::missingValue($row->number, $column, $lidnr);
        }

        return $value;
    }

    public function requiredDate(SpreadsheetImportRow $row, string $column, ?string $lidnr = null): DateTimeImmutable
    {
        $value = $this->text($row, $column);

        if ($value === '') {
            throw LegacyCsvImportException::missingValue($row->number, $column, $lidnr);
        }

        return $this->parseDate($row->number, $column, $value, $lidnr);
    }

    public function optionalDate(SpreadsheetImportRow $row, string $column, ?string $lidnr = null): ?DateTimeImmutable
    {
        $value = $this->text($row, $column);

        return $value === '' ? null : $this->parseDate($row->number, $column, $value, $lidnr);
    }

    /**
     * @return resource
     */
    private function open(string $path)
    {
        if (!is_file($path)) {
            throw LegacyCsvImportException::fileNotFound($path);
        }

        if (!is_readable($path)) {
            throw LegacyCsvImportException::unreadableFile($path);
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw LegacyCsvImportException::unreadableFile($path);
        }

        return $handle;
    }

    /**
     * @param resource $handle
     * @param list<string> $requiredHeaders
     * @return list<string>
     */
    private function headers($handle, array $requiredHeaders): array
    {
        $values = fgetcsv($handle);

        if ($values === false) {
            throw LegacyCsvImportException::headerMismatch($requiredHeaders);
        }

        $headers = array_map(
            static fn (?string $value): string => trim((string) $value),
            $values,
        );

        $headers[0] = str_replace("\u{FEFF}", '', $headers[0]);

        return $headers;
    }

    /**
     * @param list<string> $headers
     * @param list<string> $requiredHeaders
     */
    private function assertRequiredHeaders(array $headers, array $requiredHeaders): void
    {
        $missingHeaders = array_values(array_diff($requiredHeaders, $headers));

        if ($missingHeaders !== []) {
            throw LegacyCsvImportException::headerMismatch($missingHeaders);
        }
    }

    /**
     * @param resource $handle
     * @param list<string> $headers
     * @return list<SpreadsheetImportRow>
     */
    private function rows($handle, array $headers): array
    {
        $rows = [];
        $lineNumber = 1;

        while (($values = fgetcsv($handle)) !== false) {
            $lineNumber++;

            if ($this->isBlank($values)) {
                continue;
            }

            $rows[] = new SpreadsheetImportRow($lineNumber, $this->associate($headers, $values));
        }

        return $rows;
    }

    /**
     * @param list<string> $headers
     * @param array<int, string|null> $values
     * @return array<string, string|null>
     */
    private function associate(array $headers, array $values): array
    {
        $associated = [];

        foreach ($headers as $index => $header) {
            $associated[$header] = $values[$index] ?? null;
        }

        return $associated;
    }

    /** @param array<int, string|null> $values */
    private function isBlank(array $values): bool
    {
        return array_all(
            $values,
            static fn (?string $value): bool => $value === null || trim($value) === '',
        );
    }

    private function parseDate(int $rowNumber, string $column, string $value, ?string $lidnr): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        if ($date === false || $this->hasParseErrors()) {
            throw LegacyCsvImportException::invalidDate($rowNumber, $column, $value, $lidnr);
        }

        return $date;
    }

    private function hasParseErrors(): bool
    {
        $errors = DateTimeImmutable::getLastErrors();

        if ($errors === false) {
            return false;
        }

        return $errors['warning_count'] > 0 || $errors['error_count'] > 0;
    }
}
