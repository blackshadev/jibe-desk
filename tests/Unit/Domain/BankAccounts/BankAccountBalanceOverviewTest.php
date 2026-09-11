<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\BankAccounts;

use App\Domain\BankAccounts\BankAccountBalanceOverview;
use App\Domain\BankStatements\StatementChainStatus;
use App\Domain\BankStatements\StatementIntegrityStatus;
use Tests\UnitTestCase;

final class BankAccountBalanceOverviewTest extends UnitTestCase
{
    public function test_it_holds_balance_overview_values(): void
    {
        $overview = new BankAccountBalanceOverview(
            bankAccountId: 1,
            name: 'Main account',
            iban: 'NL91ABNA0417164300',
            openingAmount: 1000.0,
            inflow: 500.0,
            outflow: 200.0,
            expectedClosing: 1300.0,
            lastStatementDate: '2023-01-03',
            statementIntegrityStatus: StatementIntegrityStatus::Valid,
            statementChainStatus: StatementChainStatus::Ok,
        );

        static::assertSame(1, $overview->bankAccountId);
        static::assertSame('Main account', $overview->name);
        static::assertSame('NL91ABNA0417164300', $overview->iban);
        static::assertSame(1000.0, $overview->openingAmount);
        static::assertSame(500.0, $overview->inflow);
        static::assertSame(200.0, $overview->outflow);
        static::assertSame(1300.0, $overview->expectedClosing);
        static::assertSame('2023-01-03', $overview->lastStatementDate);
        static::assertSame(StatementIntegrityStatus::Valid, $overview->statementIntegrityStatus);
        static::assertSame(StatementChainStatus::Ok, $overview->statementChainStatus);
    }

    public function test_it_allows_null_optional_values(): void
    {
        $overview = new BankAccountBalanceOverview(
            bankAccountId: 2,
            name: 'Secondary account',
            iban: 'NL91ABNA0417164301',
            openingAmount: null,
            inflow: 0.0,
            outflow: 0.0,
            expectedClosing: null,
            lastStatementDate: null,
            statementIntegrityStatus: null,
            statementChainStatus: null,
        );

        static::assertNull($overview->openingAmount);
        static::assertNull($overview->expectedClosing);
        static::assertNull($overview->lastStatementDate);
        static::assertNull($overview->statementIntegrityStatus);
        static::assertNull($overview->statementChainStatus);
    }
}
