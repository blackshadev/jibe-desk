<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PurchaseOrders\Pages;

use App\Domain\PurchaseOrders\PurchaseOrderStatus;
use App\Filament\Admin\Resources\PurchaseOrders\Actions\PurchaseOrderStateActions;
use App\Filament\Admin\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Filament\Admin\Resources\PurchaseOrders\RelationManagers\PurchaseOrderBankingTransactionsRelationManager;
use App\Filament\Admin\Resources\PurchaseOrders\RelationManagers\PurchaseOrderBookkeepingRecordsRelationManager;
use App\Models\PurchaseOrder;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Override;

final class EditPurchaseOrder extends EditRecord
{
    #[Override]
    protected static string $resource = PurchaseOrderResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            ...PurchaseOrderStateActions::make(),
            DeleteAction::make()
                ->visible(static fn (PurchaseOrder $record): bool => $record->status === PurchaseOrderStatus::Open),
        ];
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
