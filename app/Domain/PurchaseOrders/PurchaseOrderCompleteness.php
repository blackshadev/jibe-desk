<?php

declare(strict_types=1);

namespace App\Domain\PurchaseOrders;

final readonly class PurchaseOrderCompleteness
{
    /**
     * @param list<PurchaseOrderLineCompleteness> $lines
     */
    public function __construct(
        public PurchaseOrderId $id,
        public ?string $creditorName,
        public ?string $creditorIban,
        public array $lines,
    ) {}
}
