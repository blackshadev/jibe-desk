<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PurchaseOrders\Pages;

use App\Filament\Admin\Resources\PurchaseOrders\Actions\PurchaseOrderStateActions;
use App\Filament\Admin\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Filament\Admin\Resources\PurchaseOrders\RelationManagers\PurchaseOrderBankingTransactionsRelationManager;
use App\Filament\Admin\Resources\PurchaseOrders\RelationManagers\PurchaseOrderBookkeepingRecordsRelationManager;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Livewire\Attributes\On;
use Override;

final class ViewPurchaseOrder extends ViewRecord
{
    #[Override]
    protected static string $resource = PurchaseOrderResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            ...PurchaseOrderStateActions::make(),
            EditAction::make(),
        ];
    }

    #[Override]
    #[On('markedAsPaid')]
    #[On('markedAsPending')]
    public function refresh(): void
    {
    }

    #[Override]
    public function getRelationManagers(): array
    {
        return [
            PurchaseOrderBankingTransactionsRelationManager::class,
            PurchaseOrderBookkeepingRecordsRelationManager::class,
        ];
    }

    #[Override]
    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    #[Override]
    public function getContentTabLabel(): string
    {
        return __('labels.purchase_order');
    }
}
