<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\SepaBatches\Widgets;

use App\Models\SepaBatch;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\On;
use Override;

final class BatchStatsOverview extends StatsOverviewWidget
{
    public ?SepaBatch $record = null;

    #[On('refresh')]
    #[On('refreshInvoicesTable')]
    public function refreshBatch(): void
    {
        $this->record?->refresh();
    }

    #[Override]
    protected function getStats(): array
    {
        $this->record?->load('invoices.lines');

        $submittedAt = $this->record?->submitted_at;
        $submittedByName = $this->record?->submittedBy()->value('name');

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
            Stat::make(
                label: __('labels.exported_at'),
                value: $this->record?->exported_at?->format('d-m-Y H:i') ?? '—',
            ),
            Stat::make(
                label: __('labels.submitted_at'),
                value: $submittedAt === null
                    ? '—'
                    : $submittedAt->format('d-m-Y H:i'),
            )
                ->description($submittedAt === null ? null : __('labels.submitted_by') . ' ' . (is_string($submittedByName) ? $submittedByName : '—')),
        ];
    }
}
