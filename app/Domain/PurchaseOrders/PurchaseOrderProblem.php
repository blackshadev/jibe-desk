<?php

declare(strict_types=1);

namespace App\Domain\PurchaseOrders;

final readonly class PurchaseOrderProblem
{
    public function __construct(
        public PurchaseOrderId $purchaseOrderId,
        public PurchaseOrderProblemType $type,
        public ?int $lineNumber = null,
    ) {}
}
