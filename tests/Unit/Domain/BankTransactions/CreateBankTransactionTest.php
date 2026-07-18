<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\BankTransactions;

use App\Domain\BankTransactions\CreateBankTransaction;
use Tests\UnitTestCase;

final class CreateBankTransactionTest extends UnitTestCase
{
    public function test_it_creates_a_bank_transaction_with_valid_values(): void
    {
        $transaction = new CreateBankTransaction(
            date: '2024-01-15',
            amount: 150.50,
            description: 'Monthly fee',
            bankingAccountNumber: 'NL91ABNA0417164300',
            importHash: 'abc123def456',
        );

        static::assertSame('2024-01-15', $transaction->date);
        static::assertSame(150.50, $transaction->amount);
        static::assertSame('Monthly fee', $transaction->description);
        static::assertSame('NL91ABNA0417164300', $transaction->bankingAccountNumber);
        static::assertSame('abc123def456', $transaction->importHash);
    }

    public function test_it_is_readonly(): void
    {
        $transaction = new CreateBankTransaction(
            date: '2024-01-15',
            amount: 150.50,
            description: 'Monthly fee',
            bankingAccountNumber: 'NL91ABNA0417164300',
            importHash: 'abc123def456',
        );

        static::assertSame('2024-01-15', $transaction->date);
    }
}
