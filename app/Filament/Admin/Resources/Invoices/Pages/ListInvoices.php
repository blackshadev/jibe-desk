<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Invoices\Pages;

use App\Domain\Invoices\InvoiceStatus;
use App\Filament\Admin\Resources\Invoices\InvoiceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs;
use Illuminate\Database\Eloquent\Builder;
use Override;

final class ListInvoices extends ListRecords
{
    protected static string $resource = InvoiceResource::class;

    #[Override]
    public function getTabs(): array
    {
        return [
            'all' => Tabs\Tab::make(__('labels.all')),
            'open' => Tabs\Tab::make(__('labels.invoice_status.open'))
                ->modifyQueryUsing(
                    static fn (Builder $query) => $query->where('status', InvoiceStatus::Open),
                ),
            'pending' => Tabs\Tab::make(__('labels.invoice_status.pending'))
                ->modifyQueryUsing(
                    static fn (Builder $query) => $query->where('status', InvoiceStatus::Pending),
                ),
            'paid' => Tabs\Tab::make(__('labels.invoice_status.paid'))
                ->modifyQueryUsing(
                    static fn (Builder $query) => $query->where('status', InvoiceStatus::Paid),
                ),
            'declined' => Tabs\Tab::make(__('labels.invoice_status.declined'))
                ->modifyQueryUsing(
                    static fn (Builder $query) => $query->where('status', InvoiceStatus::Declined),
                ),
        ];
    }

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
