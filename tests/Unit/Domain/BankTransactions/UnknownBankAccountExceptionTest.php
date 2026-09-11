<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\BankTransactions;

use App\Domain\BankTransactions\UnknownBankAccountException;
use RuntimeException;
use Tests\UnitTestCase;

final class UnknownBankAccountExceptionTest extends UnitTestCase
{
    public function test_it_is_a_runtime_exception(): void
    {
        $subject = new UnknownBankAccountException('NL91ABNA0417164300');

        static::assertInstanceOf(RuntimeException::class, $subject);
    }

    public function test_it_has_the_account_number_and_message(): void
    {
        $subject = new UnknownBankAccountException('NL91ABNA0417164300');

        static::assertSame('NL91ABNA0417164300', $subject->iban);
        static::assertSame('Unknown bank account: NL91ABNA0417164300', $subject->getMessage());
    }
}
