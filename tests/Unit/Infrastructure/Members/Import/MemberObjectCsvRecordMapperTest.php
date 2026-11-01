<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Members\Import;

use App\Domain\Members\Import\LegacyCsvValueException;
use App\Infrastructure\Members\Import\MemberObjectCsvRecordMapper;
use App\Infrastructure\Members\Import\SpreadsheetImportRow;
use DateTimeImmutable;
use Override;
use Tests\UnitTestCase;

final class MemberObjectCsvRecordMapperTest extends UnitTestCase
{
    private CsvReaderExpectation $reader;
    private MemberObjectCsvRecordMapper $subject;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->reader = CsvReaderExpectation::create();
        $this->subject = new MemberObjectCsvRecordMapper($this->reader->mock);
    }

    public function test_map_returns_a_record_with_its_period(): void
    {
        $row = new SpreadsheetImportRow(4, [
            'lidnr' => '1001',
            'tagnummer' => '1234',
            'start_date' => '2026-03-01',
            'end_date' => '2026-08-31',
        ]);

        $this->reader->expectsRequiredText($row, 'lidnr', '1001');
        $this->reader->expectsRequiredDate($row, 'start_date', '1001', new DateTimeImmutable('2026-03-01'));
        $this->reader->expectsOptionalDate($row, 'end_date', '1001', new DateTimeImmutable('2026-08-31'));
        $this->reader->expectsRequiredTextForMember($row, 'tagnummer', '1001', '1234');

        $records = $this->subject->map([$row], 'tagnummer');

        static::assertCount(1, $records);
        static::assertSame(4, $records[0]->rowNumber);
        static::assertSame('1001', $records[0]->memberNumber);
        static::assertSame('1234', $records[0]->objectNumber);
        static::assertSame('2026-03-01', $records[0]->startDate->format('Y-m-d'));
        static::assertSame('2026-08-31', $records[0]->endDate?->format('Y-m-d'));
        static::assertFalse($records[0]->isOpenEnded());
    }

    public function test_map_treats_an_empty_end_date_as_open_ended(): void
    {
        $row = new SpreadsheetImportRow(5, [
            'lidnr' => '1001',
            'objectnummer' => '5-01',
            'start_date' => '2026-03-01',
            'end_date' => '',
        ]);

        $this->reader->expectsRequiredText($row, 'lidnr', '1001');
        $this->reader->expectsRequiredDate($row, 'start_date', '1001', new DateTimeImmutable('2026-03-01'));
        $this->reader->expectsOptionalDate($row, 'end_date', '1001', null);
        $this->reader->expectsRequiredTextForMember($row, 'objectnummer', '1001', '5-01');

        $records = $this->subject->map([$row], 'objectnummer');

        static::assertCount(1, $records);
        static::assertNull($records[0]->endDate);
        static::assertTrue($records[0]->isOpenEnded());
    }

    public function test_map_rejects_an_end_date_before_the_start_date(): void
    {
        $row = new SpreadsheetImportRow(9, [
            'lidnr' => '1001',
            'objectnummer' => '5-01',
            'start_date' => '2026-06-01',
            'end_date' => '2026-01-01',
        ]);

        $this->reader->expectsRequiredText($row, 'lidnr', '1001');
        $this->reader->expectsRequiredDate($row, 'start_date', '1001', new DateTimeImmutable('2026-06-01'));
        $this->reader->expectsOptionalDate($row, 'end_date', '1001', new DateTimeImmutable('2026-01-01'));

        $this->expectException(LegacyCsvValueException::class);
        $this->expectExceptionMessage('Row 9 (lidnr 1001): end_date "2026-01-01" is before start_date "2026-06-01".');

        $this->subject->map([$row], 'objectnummer');
    }
}
