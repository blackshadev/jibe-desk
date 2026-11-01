<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Members\Import;

use App\Domain\Members\Import\LegacyCsvFileException;
use App\Domain\Members\Import\LegacyCsvFormatException;
use App\Domain\Members\Import\LegacyCsvImportException;
use App\Domain\Members\Import\LegacyCsvValueException;
use Tests\UnitTestCase;

final class LegacyCsvImportExceptionTest extends UnitTestCase
{
    public function test_every_failure_is_a_legacy_csv_import_exception(): void
    {
        static::assertInstanceOf(
            LegacyCsvImportException::class,
            LegacyCsvFileException::fileNotFound('members.csv'),
        );
        static::assertInstanceOf(
            LegacyCsvImportException::class,
            LegacyCsvFormatException::headerMismatch(['lidnr']),
        );
        static::assertInstanceOf(
            LegacyCsvImportException::class,
            LegacyCsvValueException::unknownLidnr(7, '124175'),
        );
    }

    public function test_a_row_failure_carries_the_row_and_lidnr(): void
    {
        $subject = LegacyCsvValueException::unknownLidnr(7, '124175');

        static::assertSame(7, $subject->rowNumber);
        static::assertSame('124175', $subject->lidnr);
        static::assertSame('Row 7 (lidnr 124175): lidnr "124175" does not belong to a member.', $subject->getMessage());
    }

    public function test_a_row_failure_without_a_lidnr_names_only_the_row(): void
    {
        $subject = LegacyCsvFormatException::missingValue(4, 'lidnr');

        static::assertSame(4, $subject->rowNumber);
        static::assertNull($subject->lidnr);
        static::assertSame('Row 4: column "lidnr" is required but empty.', $subject->getMessage());
    }
}
