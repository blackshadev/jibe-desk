<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PurchaseOrders\Pages;

use App\Domain\PurchaseOrders\PurchaseOrderStatus;
use App\Filament\Admin\Resources\PurchaseOrders\PurchaseOrderResource;
use Carbon\CarbonImmutable;
use Filament\Resources\Pages\CreateRecord;
use Override;

final class CreatePurchaseOrder extends CreateRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    #[Override]
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['date'] ??= CarbonImmutable::now();
        $data['status'] = PurchaseOrderStatus::Open;

        return $data;
    }

    protected function afterFill(): void
    {
        $this->data['date'] = CarbonImmutable::now();
        $this->data['status'] = PurchaseOrderStatus::Open;
    }
}
