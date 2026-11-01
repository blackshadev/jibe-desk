<?php

declare(strict_types=1);

namespace App\Infrastructure\Bookkeeping\CostCenters\Import;

use App\Domain\Bookkeeping\CostCenters\Import\CostCenterImportFormatException;
use App\Infrastructure\Members\Import\SpreadsheetImportRow;

final readonly class GrootboekRecordMapper extends CostCenterCsvRecordMapper
{
    private const array REQUIRED_HEADERS = [
        'Rekening',
        'Omschrijving',
        'B/W',
        'O/K/N',
        'D/C',
        'BTW',
        'Nr',
        'Verdichting',
    ];

    /** @return list<GrootboekRecord> */
    public function map(string $path): array
    {
        $records = array_map(
            fn (SpreadsheetImportRow $row): GrootboekRecord => $this->mapRow($path, $row),
            $this->rows($path, self::REQUIRED_HEADERS),
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

    private function mapRow(string $path, SpreadsheetImportRow $row): GrootboekRecord
    {
        $accountNumber = $this->requiredValue($path, $row, 'Rekening', 'Rekening');
        $title = $this->requiredValue($path, $row, 'Omschrijving', 'Omschrijving');
        $verdichtingNumber = $this->requiredValue($path, $row, 'Nr', 'Nr');
        $verdichtingName = $this->requiredValue($path, $row, 'Verdichting', 'Verdichting');

        return new GrootboekRecord(
            lineNumber: $row->number,
            accountNumber: $accountNumber,
            title: $title,
            verdichtingNumber: $verdichtingNumber,
            verdichtingName: $verdichtingName,
        );
    }
}
