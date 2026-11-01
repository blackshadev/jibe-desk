<?php

declare(strict_types=1);

namespace App\Infrastructure\Bookkeeping\CostCenters\Import;

use App\Domain\Bookkeeping\CostCenters\Import\CostCenterImportFileException;
use App\Domain\Bookkeeping\CostCenters\Import\CostCenterImportFormatException;
use App\Domain\Members\Import\CsvFileException;
use App\Domain\Members\Import\CsvFormatException;
use App\Infrastructure\Members\Import\CsvFileReader;
use App\Infrastructure\Members\Import\CsvReader;
use App\Infrastructure\Members\Import\SpreadsheetImportRow;

abstract readonly class CostCenterCsvRecordMapper
{
    public function __construct(
        private CsvReader $reader = new CsvFileReader(delimiter: ';', sourceEncoding: 'ISO-8859-1'),
    ) {}

    /**
     * @param non-empty-list<string> $headers
     * @return list<SpreadsheetImportRow>
     */
    protected function rows(string $path, array $headers): array
    {
        if (!is_file($path)) {
            throw CostCenterImportFileException::fileNotFound($path);
        }

        if (!is_readable($path)) {
            throw CostCenterImportFileException::unreadableFile($path);
        }

        try {
            return $this->reader->read($path, $headers);
        } catch (CsvFileException) {
            throw CostCenterImportFileException::unreadableFile($path);
        } catch (CsvFormatException) {
            throw CostCenterImportFormatException::headerMismatch($path, $headers);
        }
    }

    protected function requiredValue(
        string $path,
        SpreadsheetImportRow $row,
        string $column,
        string $label,
    ): string {
        $value = $this->reader->text($row, $column);

        if ($value === '') {
            throw CostCenterImportFormatException::missingValue($path, $row->number, $label);
        }

        return $value;
    }

    /**
     * @param list<string> $values
     * @return list<string>
     */
    protected static function duplicates(array $values): array
    {
        $counts = array_count_values($values);

        return array_keys(array_filter($counts, static fn (int $count): bool => $count > 1));
    }
}
