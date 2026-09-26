<?php

declare(strict_types=1);

namespace App\Domain\PurchaseOrders;

final readonly class PurchaseOrderLineCompleteness
{
    public function __construct(
        public ?int $costCenterId,
    ) {}
}
