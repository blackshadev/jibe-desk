<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\InvoiceBatches\Widgets;

use App\Models\InvoiceBatch;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Override;

final class BatchStatsOverview extends StatsOverviewWidget
{
    public ?InvoiceBatch $record = null;

    #[Override]
    protected function getStats(): array
    {
        $this->record?->load('invoices.lines');

        return [
            Stat::make(
                label: __('labels.invoice_count'),
                value: $this->record->invoice_count ?? 0,
            ),
            Stat::make(
                label: __('labels.open_total'),
                value: (string) ($this->record->openTotal ?? 0),
            ),
            Stat::make(
                label: __('labels.total'),
                value: (string) ($this->record->total ?? 0),
            ),
        ];
    }
}
