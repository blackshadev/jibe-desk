<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\BankTransactions;

use App\Domain\BankTransactions\CouldNotCompleteTransaction;
use RuntimeException;
use Tests\UnitTestCase;

final class CouldNotCompleteTransactionTest extends UnitTestCase
{
    public function test_it_is_a_runtime_exception(): void
    {
        $subject = new CouldNotCompleteTransaction();

        static::assertInstanceOf(RuntimeException::class, $subject);
    }

    public function test_it_has_default_message(): void
    {
        $subject = new CouldNotCompleteTransaction();

        static::assertSame('Cannot complete banking transaction: unmatched amount must be 0.', $subject->getMessage());
    }
}
