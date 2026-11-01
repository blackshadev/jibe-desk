<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Bookkeeping\CostCenters\Import;

use App\Domain\Bookkeeping\CostCenters\Import\CostCenterImportFileException;
use Tests\UnitTestCase;

final class CostCenterImportFileExceptionTest extends UnitTestCase
{
    public function test_file_not_found_names_the_path(): void
    {
        $exception = CostCenterImportFileException::fileNotFound('/tmp/missing.csv');

        static::assertSame('CSV file not found: /tmp/missing.csv', $exception->getMessage());
    }

    public function test_unreadable_file_names_the_path(): void
    {
        $exception = CostCenterImportFileException::unreadableFile('/tmp/unreadable.csv');

        static::assertSame('CSV file could not be read: /tmp/unreadable.csv', $exception->getMessage());
    }
}
