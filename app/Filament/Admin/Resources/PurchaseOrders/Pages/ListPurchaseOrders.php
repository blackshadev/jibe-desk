<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PurchaseOrders\Pages;

use App\Domain\PurchaseOrders\PurchaseOrderStatus;
use App\Filament\Admin\Resources\PurchaseOrders\PurchaseOrderResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs;
use Illuminate\Database\Eloquent\Builder;
use Override;

final class ListPurchaseOrders extends ListRecords
{
    protected static string $resource = PurchaseOrderResource::class;

    #[Override]
    public function getTabs(): array
    {
        return [
            'all' => Tabs\Tab::make(__('labels.all')),
            'open' => Tabs\Tab::make(__('labels.purchase_order_status.open'))
                ->modifyQueryUsing(
                    static fn (Builder $query) => $query->where('status', PurchaseOrderStatus::Open),
                ),
            'pending' => Tabs\Tab::make(__('labels.purchase_order_status.pending'))
                ->modifyQueryUsing(
                    static fn (Builder $query) => $query->where('status', PurchaseOrderStatus::Pending),
                ),
            'paid' => Tabs\Tab::make(__('labels.purchase_order_status.paid'))
                ->modifyQueryUsing(
                    static fn (Builder $query) => $query->where('status', PurchaseOrderStatus::Paid),
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
