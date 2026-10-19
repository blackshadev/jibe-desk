<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PurchaseOrders\Pages;

use App\Domain\Billing\PurchaseOrders\PurchaseOrderApprovalStatus;
use App\Domain\Billing\PurchaseOrders\PurchaseOrderStatus;
use App\Filament\Admin\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs;
use Illuminate\Database\Eloquent\Builder;
use Override;

final class ListPurchaseOrders extends ListRecords
{
    #[Override]
    protected static string $resource = PurchaseOrderResource::class;

    #[Override]
    public function getTabs(): array
    {
        return [
            'all' => Tabs\Tab::make(__('labels.all')),
            'unapproved' => Tabs\Tab::make(__('labels.purchase_order_status.unapproved'))
                ->badge(PurchaseOrder::query()->where('approval_status', PurchaseOrderApprovalStatus::Pending)->count())
                ->modifyQueryUsing(
                    static fn (Builder $query) => $query->where('approval_status', PurchaseOrderApprovalStatus::Pending),
                ),
            'open' => Tabs\Tab::make(__('labels.purchase_order_status.open'))
                ->badge(PurchaseOrder::query()->where('status', PurchaseOrderStatus::Open)->count())
                ->modifyQueryUsing(
                    static fn (Builder $query) => $query->where('status', PurchaseOrderStatus::Open),
                ),
            'pending' => Tabs\Tab::make(__('labels.purchase_order_status.pending'))
                ->badge(PurchaseOrder::query()->where('status', PurchaseOrderStatus::Pending)->count())
                ->modifyQueryUsing(
                    static fn (Builder $query) => $query->where('status', PurchaseOrderStatus::Pending),
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
