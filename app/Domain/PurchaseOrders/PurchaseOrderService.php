<?php

declare(strict_types=1);

namespace App\Domain\PurchaseOrders;

use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface PurchaseOrderService
{
    public function markAsPending(PurchaseOrderIdList $id): void;

    public function markAsPaid(PurchaseOrderIdList $ids): void;

    public function markAsDeclined(PurchaseOrderIdList $ids): void;
}
