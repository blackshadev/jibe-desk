<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Members\Import;

use App\Domain\Members\Import\LegacyCsvFileException;
use Tests\UnitTestCase;

final class LegacyCsvFileExceptionTest extends UnitTestCase
{
    public function test_a_missing_file_names_the_path(): void
    {
        $subject = LegacyCsvFileException::fileNotFound('/tmp/members.csv');

        static::assertSame('CSV file not found: /tmp/members.csv', $subject->getMessage());
        static::assertNull($subject->rowNumber);
        static::assertNull($subject->lidnr);
    }

    public function test_an_unreadable_file_names_the_path(): void
    {
        $subject = LegacyCsvFileException::unreadableFile('/tmp/members.csv');

        static::assertSame('CSV file could not be read: /tmp/members.csv', $subject->getMessage());
        static::assertNull($subject->rowNumber);
    }
}
