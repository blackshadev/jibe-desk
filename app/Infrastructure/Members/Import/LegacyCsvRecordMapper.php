<?php

declare(strict_types=1);

namespace App\Infrastructure\Members\Import;

use App\Domain\Members\Import\LegacyCsvImportException;
use DateTimeImmutable;

final readonly class LegacyCsvRecordMapper
{
    public function __construct(
        private LegacyCsvReader $reader = new LegacyCsvReader(),
    ) {}

    /**
     * @param list<SpreadsheetImportRow> $rows
     * @return list<LegacyCsvRecord>
     */
    public function map(array $rows, string $numberColumn): array
    {
        return array_map(
            fn (SpreadsheetImportRow $row): LegacyCsvRecord => $this->mapRow($row, $numberColumn),
            $rows,
        );
    }

    private function mapRow(SpreadsheetImportRow $row, string $numberColumn): LegacyCsvRecord
    {
        $lidnr = $this->reader->requiredText($row, 'lidnr');
        $startDate = $this->reader->requiredDate($row, 'start_date', $lidnr);
        $endDate = $this->reader->optionalDate($row, 'end_date', $lidnr);

        $this->assertEndDoesNotPrecedeStart($row, $lidnr, $startDate, $endDate);

        return new LegacyCsvRecord(
            rowNumber: $row->number,
            lidnr: $lidnr,
            number: $this->reader->requiredText($row, $numberColumn, $lidnr),
            startDate: $startDate,
            endDate: $endDate,
        );
    }

    private function assertEndDoesNotPrecedeStart(
        SpreadsheetImportRow $row,
        string $lidnr,
        DateTimeImmutable $startDate,
        ?DateTimeImmutable $endDate,
    ): void {
        if ($endDate === null || $endDate >= $startDate) {
            return;
        }

        throw LegacyCsvImportException::endBeforeStart(
            $row->number,
            $lidnr,
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d'),
        );
    }
}
