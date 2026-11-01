<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Members\Import;

use App\Domain\Members\Import\MemberImportException;
use Tests\UnitTestCase;

final class MemberImportExceptionTest extends UnitTestCase
{
    public function test_row_exception_carries_row_column_and_value(): void
    {
        $subject = MemberImportException::invalidIban(7, 'NL00BANK0123456789');

        static::assertSame(7, $subject->rowNumber);
        static::assertSame('Banknummer 1', $subject->column);
        static::assertSame('NL00BANK0123456789', $subject->value);
        static::assertSame('Row 7, column "Banknummer 1", value "NL00BANK0123456789" is not a valid IBAN.', $subject->getMessage());
    }

    public function test_precondition_exception_has_no_row(): void
    {
        $subject = MemberImportException::membersTableNotEmpty();

        static::assertNull($subject->rowNumber);
        static::assertNull($subject->column);
        static::assertNull($subject->value);
    }

    public function test_header_mismatch_lists_missing_columns(): void
    {
        $subject = MemberImportException::headerMismatch(['Lidnr', 'BIC 1']);

        static::assertSame('Spreadsheet is missing required columns: Lidnr, BIC 1', $subject->getMessage());
    }

    public function test_duplicate_lidnr_carries_the_row_and_value(): void
    {
        $subject = MemberImportException::duplicateLidnr(8, '1001');

        static::assertSame(8, $subject->rowNumber);
        static::assertSame('Lidnr', $subject->column);
        static::assertSame('1001', $subject->value);
        static::assertSame(
            'Row 8, column "Lidnr", value "1001" appears more than once in the spreadsheet.',
            $subject->getMessage(),
        );
    }
}
