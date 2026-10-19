<?php

declare(strict_types=1);

namespace App\Domain\Billing\PurchaseOrders;

use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface PurchaseOrderService
{
    public function acceptApproval(PurchaseOrderIdList $ids): void;

    public function declineApproval(PurchaseOrderIdList $ids, ?string $reason = null): void;

    public function markAsPaymentPending(PurchaseOrderIdList $ids): void;

    public function markAsPaid(PurchaseOrderIdList $ids): void;

    public function markAsPaymentDeclined(PurchaseOrderIdList $ids, ?string $reason = null): void;
}
