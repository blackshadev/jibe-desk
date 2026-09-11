<?php

declare(strict_types=1);

namespace App\Infrastructure\BankAccounts;

use App\Domain\BankAccounts\BankAccountBalanceOverview;
use App\Domain\BankAccounts\BankAccountBalanceRepository;
use App\Domain\BankStatements\StatementChainStatus;
use App\Domain\BankStatements\StatementIntegrityStatus;
use App\Models\BankAccount;
use App\Models\BankAccountYearBalance;
use App\Models\BankingTransaction;
use App\Models\BankStatement;
use Illuminate\Support\Collection;
use Override;

final readonly class BankAccountBalanceDbRepository implements BankAccountBalanceRepository
{
    #[Override]
    public function getOverviewForYear(int $year): array
    {
        $accounts = BankAccount::query()
            ->where('active', true)
            ->orderBy('name')
            ->get();

        if ($accounts->isEmpty()) {
            return [];
        }

        $accountIds = $accounts->pluck('id')->all();

        $openings = BankAccountYearBalance::query()
            ->where('year', $year)
            ->whereIn('bank_account_id', $accountIds)
            ->get()
            ->keyBy('bank_account_id');

        $movements = BankingTransaction::query()
            ->whereYear('date', $year)
            ->whereIn('bank_account_id', $accountIds)
            ->groupBy('bank_account_id')
            ->selectRaw('bank_account_id')
            ->selectRaw('COALESCE(SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END), 0) as inflow')
            ->selectRaw('COALESCE(SUM(CASE WHEN amount < 0 THEN amount ELSE 0 END), 0) as outflow')
            ->get()
            ->keyBy('bank_account_id');

        $statements = BankStatement::query()
            ->whereYear('start_date', $year)
            ->whereIn('bank_account_id', $accountIds)
            ->get()
            ->groupBy('bank_account_id');

        return $accounts->map(function (BankAccount $account) use ($openings, $movements, $statements): BankAccountBalanceOverview {
            $opening = $openings->get($account->id);
            $movement = $movements->get($account->id);
            $accountStatements = $statements->get($account->id, collect());

            $inflow = $movement === null ? 0.0 : (float) $movement->getAttribute('inflow');
            $outflow = $movement === null ? 0.0 : (float) $movement->getAttribute('outflow');

            $openingAmount = $opening === null ? null : (float) $opening->opening_amount;

            return new BankAccountBalanceOverview(
                bankAccountId: $account->id,
                name: $account->name,
                iban: $account->iban,
                openingAmount: $openingAmount,
                inflow: $inflow,
                outflow: $outflow,
                expectedClosing: $openingAmount === null ? null : $openingAmount + $inflow + $outflow,
                lastStatementDate: $this->lastStatementDate($accountStatements),
                statementIntegrityStatus: $this->integrityStatus($accountStatements),
                statementChainStatus: $this->chainStatus($accountStatements),
            );
        })->all();
    }

    /**
     * @param \Illuminate\Support\Collection<int, BankStatement> $statements
     */
    private function lastStatementDate(Collection $statements): ?string
    {
        if ($statements->isEmpty()) {
            return null;
        }

        return $statements->max(static fn (BankStatement $statement): string => $statement->end_date->format('Y-m-d'));
    }

    /**
     * @param \Illuminate\Support\Collection<int, BankStatement> $statements
     */
    private function integrityStatus(Collection $statements): ?StatementIntegrityStatus
    {
        if ($statements->isEmpty()) {
            return null;
        }

        if ($statements->contains(static fn (BankStatement $statement): bool => $statement->integrity_status === StatementIntegrityStatus::Mismatch)) {
            return StatementIntegrityStatus::Mismatch;
        }

        return StatementIntegrityStatus::Valid;
    }

    /**
     * @param \Illuminate\Support\Collection<int, BankStatement> $statements
     */
    private function chainStatus(Collection $statements): ?StatementChainStatus
    {
        if ($statements->isEmpty()) {
            return null;
        }

        if ($statements->contains(static fn (BankStatement $statement): bool => $statement->chain_status === StatementChainStatus::Broken)) {
            return StatementChainStatus::Broken;
        }

        if ($statements->contains(static fn (BankStatement $statement): bool => $statement->chain_status === StatementChainStatus::Baseline)) {
            return StatementChainStatus::Baseline;
        }

        return StatementChainStatus::Ok;
    }
}
