<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Members\Import;

use App\Domain\Members\Import\LegacyCsvImportException;
use Tests\UnitTestCase;

final class LegacyCsvImportExceptionTest extends UnitTestCase
{
    public function test_a_row_failure_carries_the_row_and_lidnr(): void
    {
        $subject = LegacyCsvImportException::unknownLidnr(7, '124175');

        static::assertSame(7, $subject->rowNumber);
        static::assertSame('124175', $subject->lidnr);
        static::assertSame('Row 7 (lidnr 124175): lidnr "124175" does not belong to a member.', $subject->getMessage());
    }

    public function test_a_row_failure_without_a_lidnr_names_only_the_row(): void
    {
        $subject = LegacyCsvImportException::missingValue(4, 'lidnr');

        static::assertSame(4, $subject->rowNumber);
        static::assertNull($subject->lidnr);
        static::assertSame('Row 4: column "lidnr" is required but empty.', $subject->getMessage());
    }

    public function test_a_precondition_failure_has_no_row(): void
    {
        $subject = LegacyCsvImportException::missingMemberObjectType('Tag');

        static::assertNull($subject->rowNumber);
        static::assertNull($subject->lidnr);
        static::assertSame('Member object type "Tag" does not exist.', $subject->getMessage());
    }

    public function test_a_header_mismatch_lists_the_missing_columns(): void
    {
        $subject = LegacyCsvImportException::headerMismatch(['start_date', 'end_date']);

        static::assertSame('CSV is missing required columns: start_date, end_date', $subject->getMessage());
    }

    public function test_an_end_before_start_names_both_dates(): void
    {
        $subject = LegacyCsvImportException::endBeforeStart(9, '1001', '2026-06-01', '2026-01-01');

        static::assertSame(
            'Row 9 (lidnr 1001): end_date "2026-01-01" is before start_date "2026-06-01".',
            $subject->getMessage(),
        );
    }
}
