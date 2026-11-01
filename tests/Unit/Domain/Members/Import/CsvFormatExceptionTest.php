<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Members\Import;

use App\Domain\Members\Import\CsvFormatException;
use Tests\UnitTestCase;

final class CsvFormatExceptionTest extends UnitTestCase
{
    public function test_a_header_mismatch_lists_the_missing_columns(): void
    {
        $subject = CsvFormatException::headerMismatch(['start_date', 'end_date']);

        static::assertSame('CSV is missing required columns: start_date, end_date', $subject->getMessage());
        static::assertNull($subject->rowNumber);
    }

    public function test_a_missing_value_names_the_row_column_and_lidnr(): void
    {
        $subject = CsvFormatException::missingValue(5, 'end_date', '1001');

        static::assertSame(5, $subject->rowNumber);
        static::assertSame('1001', $subject->lidnr);
        static::assertSame('Row 5 (lidnr 1001): column "end_date" is required but empty.', $subject->getMessage());
    }

    public function test_an_invalid_date_names_the_value_and_expected_format(): void
    {
        $subject = CsvFormatException::invalidDate(6, 'start_date', '01-03-2026', '1001');

        static::assertSame(6, $subject->rowNumber);
        static::assertSame('1001', $subject->lidnr);
        static::assertSame(
            'Row 6 (lidnr 1001): column "start_date", value "01-03-2026" is not a valid Y-m-d date.',
            $subject->getMessage(),
        );
    }
}
