<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Bookkeeping\CostCenters\Import;

use App\Domain\Bookkeeping\CostCenters\Import\CostCenterImportFormatException;
use Tests\UnitTestCase;

final class CostCenterImportFormatExceptionTest extends UnitTestCase
{
    public function test_header_mismatch_lists_the_expected_header(): void
    {
        $exception = CostCenterImportFormatException::headerMismatch('/tmp/gb.csv', ['Rekening', 'Omschrijving']);

        static::assertStringContainsString('/tmp/gb.csv', $exception->getMessage());
        static::assertStringContainsString('Rekening;Omschrijving', $exception->getMessage());
    }

    public function test_missing_value_names_the_line_and_column(): void
    {
        $exception = CostCenterImportFormatException::missingValue('/tmp/gb.csv', 4, 'Nr');

        static::assertSame(
            'File "/tmp/gb.csv", line 4: column "Nr" is required but empty.',
            $exception->getMessage(),
        );
    }

    public function test_duplicate_account_numbers_are_sorted_and_unique(): void
    {
        $exception = CostCenterImportFormatException::duplicateAccountNumbers(['300', '100', '300', '100']);

        static::assertSame(
            'The grootboek file contains duplicate account numbers: 100, 300',
            $exception->getMessage(),
        );
    }

    public function test_duplicate_group_numbers_are_sorted_and_unique(): void
    {
        $exception = CostCenterImportFormatException::duplicateGroupNumbers(['21', '20', '21']);

        static::assertSame(
            'A group file contains duplicate numbers: 20, 21',
            $exception->getMessage(),
        );
    }
}
