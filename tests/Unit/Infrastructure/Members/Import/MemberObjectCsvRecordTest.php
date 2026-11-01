<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Members\Import;

use App\Infrastructure\Members\Import\MemberObjectCsvRecord;
use DateTimeImmutable;
use Tests\UnitTestCase;

final class MemberObjectCsvRecordTest extends UnitTestCase
{
    public function test_it_reports_a_closed_period(): void
    {
        $subject = new MemberObjectCsvRecord(
            rowNumber: 3,
            memberNumber: '1001',
            objectNumber: '5-01',
            startDate: new DateTimeImmutable('2026-03-01'),
            endDate: new DateTimeImmutable('2026-08-31'),
        );

        static::assertSame(3, $subject->rowNumber);
        static::assertSame('1001', $subject->memberNumber);
        static::assertSame('5-01', $subject->objectNumber);
        static::assertSame('2026-03-01', $subject->startDate->format('Y-m-d'));
        static::assertFalse($subject->isOpenEnded());
    }

    public function test_it_reports_an_open_ended_period(): void
    {
        $subject = new MemberObjectCsvRecord(
            rowNumber: 4,
            memberNumber: '1001',
            objectNumber: '1234',
            startDate: new DateTimeImmutable('2026-03-01'),
            endDate: null,
        );

        static::assertNull($subject->endDate);
        static::assertTrue($subject->isOpenEnded());
    }
}
