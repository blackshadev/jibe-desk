<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\BankStatements;

use App\Domain\BankAccounts\BankAccountId;
use App\Domain\BankStatements\BankStatementId;
use App\Domain\BankStatements\DetermineBankStatementChainStatusImpl;
use App\Domain\BankStatements\DetermineBankStatementChainStatusInput;
use App\Domain\BankStatements\PreviousStatement;
use App\Domain\BankStatements\StatementChainStatus;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DetermineBankStatementChainStatusImplTest extends TestCase
{
    #[Test]
    public function test_it_marks_the_first_statement_as_baseline(): void
    {
        $repository = BankStatementRepositoryExpectation::create();
        $input = new DetermineBankStatementChainStatusInput(
            id: BankStatementId::create(1),
            accountId: BankAccountId::create(2),
            startDate: new DateTimeImmutable('2023-01-01'),
            openingBalance: 1000.0,
        );

        $repository->expectsFindPrevious($input->accountId, $input->startDate, null);
        $repository->expectsUpdateChain($input->id, StatementChainStatus::Baseline);

        $service = new DetermineBankStatementChainStatusImpl($repository->mock);
        $result = $service->determine($input);

        static::assertSame(StatementChainStatus::Baseline, $result);
    }

    #[Test]
    public function test_it_marks_a_statement_as_ok_when_previous_closing_balance_matches_opening_balance(): void
    {
        $repository = BankStatementRepositoryExpectation::create();
        $input = new DetermineBankStatementChainStatusInput(
            id: BankStatementId::create(1),
            accountId: BankAccountId::create(2),
            startDate: new DateTimeImmutable('2023-01-02'),
            openingBalance: 1000.0,
        );

        $repository->expectsFindPrevious($input->accountId, $input->startDate, new PreviousStatement('1/1', 1000.0));
        $repository->expectsUpdateChain($input->id, StatementChainStatus::Ok);

        $service = new DetermineBankStatementChainStatusImpl($repository->mock);
        $result = $service->determine($input);

        static::assertSame(StatementChainStatus::Ok, $result);
    }

    #[Test]
    public function test_it_marks_a_statement_as_broken_when_previous_closing_balance_differs(): void
    {
        $repository = BankStatementRepositoryExpectation::create();
        $input = new DetermineBankStatementChainStatusInput(
            id: BankStatementId::create(1),
            accountId: BankAccountId::create(2),
            startDate: new DateTimeImmutable('2023-01-02'),
            openingBalance: 1000.0,
        );

        $repository->expectsFindPrevious($input->accountId, $input->startDate, new PreviousStatement('1/1', 900.0));
        $repository->expectsUpdateChain($input->id, StatementChainStatus::Broken);

        $service = new DetermineBankStatementChainStatusImpl($repository->mock);
        $result = $service->determine($input);

        static::assertSame(StatementChainStatus::Broken, $result);
    }
}
