<?php

declare(strict_types=1);

namespace App\Infrastructure\Bookkeeping\CostCenters\Import;

use App\Domain\Bookkeeping\CostCenters\Import\CostCenterImportFormatException;
use App\Infrastructure\Members\Import\SpreadsheetImportRow;

final readonly class HoofdverdichtingRecordMapper extends CostCenterCsvRecordMapper
{
    private const array REQUIRED_HEADERS = [
        '',
        'Hoofdverdichting',
    ];

    /** @return list<HoofdverdichtingRecord> */
    public function map(string $path): array
    {
        $records = array_map(
            fn (SpreadsheetImportRow $row): HoofdverdichtingRecord => $this->mapRow($path, $row),
            $this->rows($path, self::REQUIRED_HEADERS),
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

    private function mapRow(string $path, SpreadsheetImportRow $row): HoofdverdichtingRecord
    {
        $number = $this->requiredValue($path, $row, '', 'Nummer');
        $name = $this->requiredValue($path, $row, 'Hoofdverdichting', 'Hoofdverdichting');

        return new HoofdverdichtingRecord(
            lineNumber: $row->number,
            number: $number,
            name: $name,
        );
    }
}
