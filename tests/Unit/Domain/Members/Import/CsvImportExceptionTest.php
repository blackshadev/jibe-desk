<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Members\Import;

use App\Domain\Members\Import\CsvFileException;
use App\Domain\Members\Import\CsvFormatException;
use App\Domain\Members\Import\CsvImportException;
use App\Domain\Members\Import\CsvValueException;
use Tests\UnitTestCase;

final class CsvImportExceptionTest extends UnitTestCase
{
    public function test_every_failure_is_a_csv_import_exception(): void
    {
        static::assertInstanceOf(
            CsvImportException::class,
            CsvFileException::fileNotFound('members.csv'),
        );
        static::assertInstanceOf(
            CsvImportException::class,
            CsvFormatException::headerMismatch(['lidnr']),
        );
        static::assertInstanceOf(
            CsvImportException::class,
            CsvValueException::unknownLidnr(7, '124175'),
        );
    }

    public function test_a_row_failure_carries_the_row_and_lidnr(): void
    {
        $subject = CsvValueException::unknownLidnr(7, '124175');

        static::assertSame(7, $subject->rowNumber);
        static::assertSame('124175', $subject->lidnr);
        static::assertSame('Row 7 (lidnr 124175): lidnr "124175" does not belong to a member.', $subject->getMessage());
    }

    public function test_a_row_failure_without_a_lidnr_names_only_the_row(): void
    {
        $subject = CsvFormatException::missingValue(4, 'lidnr');

        static::assertSame(4, $subject->rowNumber);
        static::assertNull($subject->lidnr);
        static::assertSame('Row 4: column "lidnr" is required but empty.', $subject->getMessage());
    }
}
