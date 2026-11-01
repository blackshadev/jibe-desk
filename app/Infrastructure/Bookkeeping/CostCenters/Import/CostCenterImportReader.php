<?php

declare(strict_types=1);

namespace App\Infrastructure\Bookkeeping\CostCenters\Import;

use App\Domain\Bookkeeping\CostCenters\Import\CostCenterImportFormatException;

final readonly class CostCenterImportReader
{
    private const array GROOTBOEK_HEADERS = [
        'Rekening',
        'Omschrijving',
        'B/W',
        'O/K/N',
        'D/C',
        'BTW',
        'Nr   ',
        'Verdichting',
    ];

    private const array VERDICHTING_HEADERS = [
        'Nummer ',
        'Verdichting',
        '',
        'Hoofdverdichting',
    ];

    private const array HOOFDVERDICHTING_HEADERS = [
        '',
        'Hoofdverdichting',
    ];

    public function __construct(
        private CostCenterCsvReader $csvReader,
    ) {}

    /** @return list<GrootboekRecord> */
    public function readGrootboek(string $path): array
    {
        $records = array_map(
            static fn (array $row): GrootboekRecord => new GrootboekRecord(
                lineNumber: $row['lineNumber'],
                accountNumber: self::required($row, 0, $path, 'Rekening'),
                title: self::required($row, 1, $path, 'Omschrijving'),
                verdichtingNumber: self::required($row, 6, $path, 'Nr'),
                verdichtingName: self::required($row, 7, $path, 'Verdichting'),
            ),
            $this->csvReader->read($path, self::GROOTBOEK_HEADERS),
        );

        $duplicates = self::duplicates(array_map(
            static fn (GrootboekRecord $record): string => $record->accountNumber,
            $records,
        ));

        if ($duplicates !== []) {
            throw CostCenterImportFormatException::duplicateAccountNumbers($duplicates);
        }

        return $records;
    }

    /** @return list<VerdichtingRecord> */
    public function readVerdichtingen(string $path): array
    {
        $records = array_map(
            static fn (array $row): VerdichtingRecord => new VerdichtingRecord(
                lineNumber: $row['lineNumber'],
                number: self::required($row, 0, $path, 'Nummer'),
                name: self::required($row, 1, $path, 'Verdichting'),
                parentNumber: self::required($row, 2, $path, 'Hoofdverdichting nummer'),
                parentName: self::required($row, 3, $path, 'Hoofdverdichting'),
            ),
            $this->csvReader->read($path, self::VERDICHTING_HEADERS),
        );

        $duplicates = self::duplicates(array_map(
            static fn (VerdichtingRecord $record): string => $record->number,
            $records,
        ));

        if ($duplicates !== []) {
            throw CostCenterImportFormatException::duplicateGroupNumbers($duplicates);
        }

        return $records;
    }

    /** @return list<HoofdverdichtingRecord> */
    public function readHoofdverdichtingen(string $path): array
    {
        $records = array_map(
            static fn (array $row): HoofdverdichtingRecord => new HoofdverdichtingRecord(
                lineNumber: $row['lineNumber'],
                number: self::required($row, 0, $path, 'Nummer'),
                name: self::required($row, 1, $path, 'Hoofdverdichting'),
            ),
            $this->csvReader->read($path, self::HOOFDVERDICHTING_HEADERS),
        );

        $duplicates = self::duplicates(array_map(
            static fn (HoofdverdichtingRecord $record): string => $record->number,
            $records,
        ));

        if ($duplicates !== []) {
            throw CostCenterImportFormatException::duplicateGroupNumbers($duplicates);
        }

        return $records;
    }

    /**
     * @param array{lineNumber: int, values: list<string>} $row
     */
    private static function required(array $row, int $index, string $path, string $column): string
    {
        $value = trim($row['values'][$index] ?? '');

        if ($value === '') {
            throw CostCenterImportFormatException::missingValue($path, $row['lineNumber'], $column);
        }

        return $value;
    }

    /**
     * @param list<string> $numbers
     * @return list<string>
     */
    private static function duplicates(array $numbers): array
    {
        $counts = array_count_values($numbers);

        return array_keys(array_filter($counts, static fn (int $count): bool => $count > 1));
    }
}
