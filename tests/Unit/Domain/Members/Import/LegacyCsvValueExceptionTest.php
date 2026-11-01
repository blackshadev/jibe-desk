<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Members\Import;

use App\Domain\Members\Import\LegacyCsvValueException;
use Tests\UnitTestCase;

final class LegacyCsvValueExceptionTest extends UnitTestCase
{
    public function test_an_unknown_lidnr_names_the_member(): void
    {
        $subject = LegacyCsvValueException::unknownLidnr(7, '124175');

        static::assertSame(7, $subject->rowNumber);
        static::assertSame('124175', $subject->lidnr);
        static::assertSame('Row 7 (lidnr 124175): lidnr "124175" does not belong to a member.', $subject->getMessage());
    }

    public function test_a_precondition_failure_has_no_row(): void
    {
        $subject = LegacyCsvValueException::missingMemberObjectType('Tag');

        static::assertNull($subject->rowNumber);
        static::assertNull($subject->lidnr);
        static::assertSame('Member object type "Tag" does not exist.', $subject->getMessage());
    }

    public function test_an_end_before_start_names_both_dates(): void
    {
        $subject = LegacyCsvValueException::endBeforeStart(9, '1001', '2026-06-01', '2026-01-01');

        static::assertSame(
            'Row 9 (lidnr 1001): end_date "2026-01-01" is before start_date "2026-06-01".',
            $subject->getMessage(),
        );
    }

    public function test_a_duplicate_record_names_the_value(): void
    {
        $subject = LegacyCsvValueException::duplicateRecord(3, '1001', '1234');

        static::assertSame('Row 3 (lidnr 1001): "1234" appears more than once for this member.', $subject->getMessage());
    }

    public function test_an_invalid_object_number_shows_the_expected_shape(): void
    {
        $subject = LegacyCsvValueException::invalidObjectNumber(2, '1001', '501');

        static::assertSame('Row 2 (lidnr 1001): object number "501" must look like 5-01.', $subject->getMessage());
    }

    public function test_an_overlapping_rental_names_the_storage_space(): void
    {
        $subject = LegacyCsvValueException::overlappingRental(4, '1001', '5-01');

        static::assertSame('Row 4 (lidnr 1001): storage space "5-01" is already rented in this period.', $subject->getMessage());
    }

    public function test_an_invalid_date_option_names_the_option(): void
    {
        $subject = LegacyCsvValueException::invalidDateOption('billing-start', 'next year');

        static::assertSame('Option --billing-start must be a date (Y-m-d), got "next year".', $subject->getMessage());
        static::assertNull($subject->rowNumber);
    }
}
