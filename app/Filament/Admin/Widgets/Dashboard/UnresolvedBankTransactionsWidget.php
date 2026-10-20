<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets\Dashboard;

use App\Domain\Authorization\RoleName;
use App\Domain\Bookkeeping\Banking\BankTransactions\BankTransactionStatus;
use App\Domain\Bookkeeping\Banking\BankTransactions\ResolveStatus;
use App\Domain\SharedKernel\PriceFormatter;
use App\Filament\Admin\Widgets\Dashboard\Concerns\VisibleForRoles;
use App\Models\BankTransaction;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Override;

final class UnresolvedBankTransactionsWidget extends StatsOverviewWidget
{
    use VisibleForRoles;

    #[Override]
    protected static ?int $sort = 14;

    #[Override]
    protected ?string $pollingInterval = null;

    /**
     * @var array<string, int>
     */
    #[Override]
    protected int|string|array $columnSpan = [
        'default' => 12,
        'lg' => 4,
    ];

    /**
     * @return list<RoleName>
     */
    #[Override]
    protected static function roles(): array
    {
        return [RoleName::FinancialAdministration];
    }

    #[Override]
    protected function getStats(): array
    {
        $query = BankTransaction::query()
            ->where('status', BankTransactionStatus::Open)
            ->where('resolve_status', ResolveStatus::Unresolved);

        return [
            Stat::make(__('labels.unresolved_bank_transactions'), (string) (clone $query)->count())
                ->description(PriceFormatter::format((float) (clone $query)->sum('amount')))
                ->color('primary'),
        ];
    }
}
