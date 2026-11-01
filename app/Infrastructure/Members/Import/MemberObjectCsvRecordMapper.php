<?php

declare(strict_types=1);

namespace App\Infrastructure\Members\Import;

use App\Domain\Members\Import\LegacyCsvValueException;
use DateTimeImmutable;

final readonly class MemberObjectCsvRecordMapper
{
    public function __construct(
        private CsvReader $reader = new LegacyCsvReader(),
    ) {}

    /**
     * @param list<SpreadsheetImportRow> $rows
     * @return list<MemberObjectCsvRecord>
     */
    public function map(array $rows, string $objectNumberColumn): array
    {
        return array_map(
            fn (SpreadsheetImportRow $row): MemberObjectCsvRecord => $this->mapRow($row, $objectNumberColumn),
            $rows,
        );
    }

    private function mapRow(SpreadsheetImportRow $row, string $objectNumberColumn): MemberObjectCsvRecord
    {
        $memberNumber = $this->reader->requiredText($row, 'lidnr');
        $startDate = $this->reader->requiredDate($row, 'start_date', $memberNumber);
        $endDate = $this->reader->optionalDate($row, 'end_date', $memberNumber);

        self::assertEndDoesNotPrecedeStart($row, $memberNumber, $startDate, $endDate);

        return new MemberObjectCsvRecord(
            rowNumber: $row->number,
            memberNumber: $memberNumber,
            objectNumber: $this->reader->requiredText($row, $objectNumberColumn, $memberNumber),
            startDate: $startDate,
            endDate: $endDate,
        );
    }

    private static function assertEndDoesNotPrecedeStart(
        SpreadsheetImportRow $row,
        string $memberNumber,
        DateTimeImmutable $startDate,
        ?DateTimeImmutable $endDate,
    ): void {
        if ($endDate === null || $endDate >= $startDate) {
            return;
        }

        throw LegacyCsvValueException::endBeforeStart(
            $row->number,
            $memberNumber,
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d'),
        );
    }
}
