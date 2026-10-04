<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PurchaseOrders\Pages;

use App\Filament\Admin\Resources\PurchaseOrders\Actions\PurchaseOrderStateActions;
use App\Filament\Admin\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Filament\Admin\Resources\PurchaseOrders\RelationManagers\PurchaseOrderBankTransactionsRelationManager;
use App\Filament\Admin\Resources\PurchaseOrders\RelationManagers\PurchaseOrderBookkeepingRecordsRelationManager;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Livewire\Attributes\On;
use Override;

final class ViewPurchaseOrder extends ViewRecord
{
    #[Override]
    protected static string $resource = PurchaseOrderResource::class;

    #[Override]
    public static function authorizeResourceAccess(): void
    {

        abort_unless(
            static::getResource()::canAccess() || (auth()->user()?->isMember() ?? false),
            403,
        );
    }

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            ActionGroup::make([
                ...PurchaseOrderStateActions::make(),
                DeleteAction::make(),
            ])
                ->button()
                ->color('gray'),
        ];
    }

    #[Override]
    #[On('refresh')]
    #[On('markedAsPaid')]
    #[On('markedAsPending')]
    #[On('markedAsDeclined')]
    public function refresh(): void
    {
    }

    #[Override]
    protected function getAllRelationManagers(): array
    {
        return [
            PurchaseOrderBankTransactionsRelationManager::class,
            PurchaseOrderBookkeepingRecordsRelationManager::class,
        ];
    }

    #[Override]
    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return auth()->user()->isAdmin();
    }

    #[Override]
    public function getContentTabLabel(): string
    {
        return __('labels.purchase_order');
    }
}
