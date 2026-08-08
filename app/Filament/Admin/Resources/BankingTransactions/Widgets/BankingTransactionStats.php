<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankingTransactions\Widgets;

use App\Domain\Invoices\Formatters\PriceFormatter;
use App\Filament\Admin\Resources\BankingTransactions\BankingTransactionResource;
use App\Models\BankingTransaction;
use Filament\Widgets\StatsOverviewWidget;
use Livewire\Attributes\On;
use Override;

final class BankingTransactionStats extends StatsOverviewWidget
{
    public ?BankingTransaction $record = null;

    #[Override]
    protected function getStats(): array
    {
        if (!$this->record) {
            return [];
        }

        $matched = $this->record->matched_amount;
        $unmatched = $this->record->unmatched_amount;

        $stats = [
            StatsOverviewWidget\Stat::make('total', PriceFormatter::format($matched))
                ->label(__('labels.matched_transactions'))
                ->description('nog ' . PriceFormatter::format($unmatched) . ' ' . strtolower(__('labels.unmatched')))
                ->color($unmatched > 0 ? 'danger' : 'success'),
        ];

        if ($this->record->isReversal()) {
            $stats[] = StatsOverviewWidget\Stat::make('reversal', '↩')
                ->label(__('labels.is_reversal'))
                ->description(__('labels.reversed_by', ['id' => $this->record->reversed_by_transaction_id]))
                ->url(BankingTransactionResource::getUrl('view', ['record' => $this->record->reversed_by_transaction_id]))
                ->color('danger');
        }

        if ($this->record->isReversed()) {
            $stats[] = StatsOverviewWidget\Stat::make('reversed', '↪')
                ->label(__('labels.reversed'))
                ->description(__('labels.has_reversal', ['id' => $this->record->reversedTransaction->id]))
                ->url(BankingTransactionResource::getUrl('view', ['record' => $this->record->reversedTransaction->id]))
                ->color('danger');
        }

        return $stats;
    }

    #[On('refresh')]
    public function refresh(): void
    {
    }
}
