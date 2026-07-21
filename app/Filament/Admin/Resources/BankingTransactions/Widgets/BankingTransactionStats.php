<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankingTransactions\Widgets;

use App\Domain\Invoices\Formatters\PriceFormatter;
use App\Models\BankingTransaction;
use Filament\Widgets\StatsOverviewWidget;

final class BankingTransactionStats extends StatsOverviewWidget
{
    public ?BankingTransaction $record = null;

    protected function getStats(): array
    {
        if (!$this->record) {
            return [];
        }

        $matched = $this->record->matched_amount;
        $unmatched = $this->record->unmatched_amount;

        return [
            StatsOverviewWidget\Stat::make('total', PriceFormatter::format($matched))
                ->label(__('labels.matched_transactions'))
                ->description('nog '. PriceFormatter::format($unmatched) . ' ' . strtolower(__('labels.unmatched')))
                ->color($unmatched > 0 ? 'danger' : 'success'),
        ];
    }
}
