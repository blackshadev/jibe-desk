<?php

declare(strict_types=1);

namespace App\Domain\PurchaseOrders;

use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface PurchaseOrderService
{
    public function markAsPending(PurchaseOrderId $id): void;

    public function markAsPaid(PurchaseOrderId $id): void;
}
