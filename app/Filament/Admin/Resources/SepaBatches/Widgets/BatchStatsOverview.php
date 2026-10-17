<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\SepaBatches\Widgets;

use App\Models\SepaBatch;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Override;

final class BatchStatsOverview extends StatsOverviewWidget
{
    public ?SepaBatch $record = null;

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
                label: __('labels.purchase_order_count'),
                value: $this->record->purchase_order_count ?? 0,
            ),
            Stat::make(
                label: __('labels.total'),
                value: (string) ($this->record->total ?? 0),
            ),
            Stat::make(
                label: __('labels.open_invoice_total'),
                value: (string) ($this->record->openInvoiceTotal ?? 0),
            ),
            Stat::make(
                label: __('labels.open_purchase_order_total'),
                value: (string) ($this->record->openPurchaseOrderTotal ?? 0),
            ),
        ];
    }
}
