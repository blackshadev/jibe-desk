<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Bookkeeping\CostCenters\Import;

use App\Domain\Bookkeeping\CostCenters\Import\CostCenterImportValueException;
use Tests\UnitTestCase;

final class CostCenterImportValueExceptionTest extends UnitTestCase
{
    public function test_unknown_parent_lists_the_verdichting_numbers(): void
    {
        $exception = CostCenterImportValueException::unknownParent(['21', '20']);

        static::assertStringContainsString('20, 21', $exception->getMessage());
    }

    public function test_parent_name_mismatch_lists_the_verdichting_numbers(): void
    {
        $exception = CostCenterImportValueException::parentNameMismatch(['11']);

        static::assertStringContainsString('hierarchy for: 11', $exception->getMessage());
    }

    public function test_unknown_verdichting_lists_accounts_and_verdichting_numbers(): void
    {
        $exception = CostCenterImportValueException::unknownVerdichting(['100', '200'], ['77']);

        static::assertStringContainsString('100, 200', $exception->getMessage());
        static::assertStringContainsString('77', $exception->getMessage());
    }

    public function test_account_name_mismatch_lists_the_account_numbers(): void
    {
        $exception = CostCenterImportValueException::accountVerdichtingNameMismatch(['300', '100']);

        static::assertStringContainsString('accounts: 100, 300', $exception->getMessage());
    }

    public function test_duplicate_qualified_numbers_are_listed(): void
    {
        $exception = CostCenterImportValueException::duplicateQualifiedNumbers(['1.10', '1.10']);

        static::assertStringContainsString('qualified group numbers: 1.10', $exception->getMessage());
    }
}
