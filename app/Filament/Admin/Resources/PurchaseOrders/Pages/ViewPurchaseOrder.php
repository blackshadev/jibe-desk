<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PurchaseOrders\Pages;

use App\Filament\Admin\Resources\PurchaseOrders\Actions\PurchaseOrderStateActions;
use App\Filament\Admin\Resources\PurchaseOrders\PurchaseOrderResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Livewire\Attributes\On;

final class ViewPurchaseOrder extends ViewRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...PurchaseOrderStateActions::make(),
            EditAction::make(),
        ];
    }

    #[On('markedAsPaid')]
    #[On('markedAsPending')]
    public function refresh(): void
    {
    }
}
