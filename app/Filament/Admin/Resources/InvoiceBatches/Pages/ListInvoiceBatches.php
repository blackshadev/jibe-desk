<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\InvoiceBatches\Pages;

use App\Domain\Invoices\InvoiceBatchStatus;
use App\Filament\Admin\Resources\InvoiceBatches\InvoiceBatchResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs;
use Illuminate\Database\Eloquent\Builder;
use Override;

final class ListInvoiceBatches extends ListRecords
{
    protected static string $resource = InvoiceBatchResource::class;

    #[Override]
    public function getTabs(): array
    {
        return [
            'all' => Tabs\Tab::make(__('labels.all')),
            'open' => Tabs\Tab::make(__('labels.batch_status.open'))
                ->modifyQueryUsing(
                    static fn (Builder $query) => $query->where('status', InvoiceBatchStatus::Open),
                ),
            'pending' => Tabs\Tab::make(__('labels.batch_status.pending'))
                ->modifyQueryUsing(
                    static fn (Builder $query) => $query->where('status', InvoiceBatchStatus::Pending),
                ),
            'completed' => Tabs\Tab::make(__('labels.batch_status.completed'))
                ->modifyQueryUsing(
                    static fn (Builder $query) => $query->where('status', InvoiceBatchStatus::Completed),
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
