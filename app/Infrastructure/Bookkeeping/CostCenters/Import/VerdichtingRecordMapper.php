<?php

declare(strict_types=1);

namespace App\Infrastructure\Bookkeeping\CostCenters\Import;

use App\Domain\Bookkeeping\CostCenters\Import\CostCenterImportFormatException;
use App\Infrastructure\Members\Import\SpreadsheetImportRow;

final readonly class VerdichtingRecordMapper extends CostCenterCsvRecordMapper
{
    private const array REQUIRED_HEADERS = [
        'Nummer',
        'Verdichting',
        '',
        'Hoofdverdichting',
    ];

    /** @return list<VerdichtingRecord> */
    public function map(string $path): array
    {
        $records = array_map(
            fn (SpreadsheetImportRow $row): VerdichtingRecord => $this->mapRow($path, $row),
            $this->rows($path, self::REQUIRED_HEADERS),
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

    private function mapRow(string $path, SpreadsheetImportRow $row): VerdichtingRecord
    {
        $number = $this->requiredValue($path, $row, 'Nummer', 'Nummer');
        $name = $this->requiredValue($path, $row, 'Verdichting', 'Verdichting');
        $parentNumber = $this->requiredValue($path, $row, '', 'Hoofdverdichting nummer');
        $parentName = $this->requiredValue($path, $row, 'Hoofdverdichting', 'Hoofdverdichting');

        return new VerdichtingRecord(
            lineNumber: $row->number,
            number: $number,
            name: $name,
            parentNumber: $parentNumber,
            parentName: $parentName,
        );
    }
}
