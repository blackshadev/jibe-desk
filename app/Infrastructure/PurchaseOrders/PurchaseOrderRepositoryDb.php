<?php

declare(strict_types=1);

namespace App\Infrastructure\PurchaseOrders;

use App\Domain\PurchaseOrders\PurchaseOrderId;
use App\Domain\PurchaseOrders\PurchaseOrderRepository;
use App\Domain\PurchaseOrders\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use Override;

final class PurchaseOrderRepositoryDb implements PurchaseOrderRepository
{
    #[Override]
    public function markAsPending(PurchaseOrderId $id): void
    {
        PurchaseOrder::query()
            ->where('id', $id->value)
            ->update(['status' => PurchaseOrderStatus::Pending]);
    }

    #[Override]
    public function markAsPaid(PurchaseOrderId $id): void
    {
        PurchaseOrder::query()
            ->where('id', $id->value)
            ->update(['status' => PurchaseOrderStatus::Paid]);
    }
}
