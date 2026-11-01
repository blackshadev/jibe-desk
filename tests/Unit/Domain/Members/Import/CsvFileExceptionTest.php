<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Members\Import;

use App\Domain\Members\Import\CsvFileException;
use Tests\UnitTestCase;

final class CsvFileExceptionTest extends UnitTestCase
{
    public function test_a_missing_file_names_the_path(): void
    {
        $subject = CsvFileException::fileNotFound('/tmp/members.csv');

        static::assertSame('CSV file not found: /tmp/members.csv', $subject->getMessage());
        static::assertNull($subject->rowNumber);
        static::assertNull($subject->lidnr);
    }

    public function test_an_unreadable_file_names_the_path(): void
    {
        $subject = CsvFileException::unreadableFile('/tmp/members.csv');

        static::assertSame('CSV file could not be read: /tmp/members.csv', $subject->getMessage());
        static::assertNull($subject->rowNumber);
    }
}
