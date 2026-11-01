<?php

declare(strict_types=1);

namespace App\Infrastructure\Members\Import;

use DateTimeImmutable;

interface CsvReader
{
    /**
     * @param list<string> $requiredHeaders
     * @return list<SpreadsheetImportRow>
     */
    public function read(string $path, array $requiredHeaders): array;

    public function text(SpreadsheetImportRow $row, string $column): string;

    public function requiredText(SpreadsheetImportRow $row, string $column, ?string $lidnr = null): string;

    public function requiredDate(SpreadsheetImportRow $row, string $column, ?string $lidnr = null): DateTimeImmutable;

    public function optionalDate(SpreadsheetImportRow $row, string $column, ?string $lidnr = null): ?DateTimeImmutable;
}
