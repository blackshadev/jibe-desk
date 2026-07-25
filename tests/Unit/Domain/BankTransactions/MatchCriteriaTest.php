<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\BankTransactions;

use App\Domain\BankTransactions\MatchCriteria;
use DateTimeImmutable;
use Tests\UnitTestCase;

final class MatchCriteriaTest extends UnitTestCase
{
    public function test_it_creates_with_valid_values(): void
    {
        $date = new DateTimeImmutable('2026-01-15');

        $criteria = new MatchCriteria(
            date: $date,
            amount: 100.50,
            bankingAccountNumber: 'NL91ABNA0417164300',
        );

        static::assertSame($date, $criteria->date);
        static::assertSame(100.50, $criteria->amount);
        static::assertSame('NL91ABNA0417164300', $criteria->bankingAccountNumber);
    }

    public function test_it_supports_negative_amount(): void
    {
        $criteria = new MatchCriteria(
            date: new DateTimeImmutable('2026-01-15'),
            amount: -50.00,
            bankingAccountNumber: 'NL91ABNA0417164300',
        );

        static::assertSame(-50.00, $criteria->amount);
    }
}
