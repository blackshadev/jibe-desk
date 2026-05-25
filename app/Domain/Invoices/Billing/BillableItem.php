<?php

declare(strict_types=1);

namespace App\Domain\Invoices\Billing;

use App\Domain\Invoices\CompoundPrice;

final readonly class BillableItem
{
    public function __construct(
        public BillableItemId $id,
        public CompoundPrice $price,
        public float $quantity,
        public string $description,
    ) {
    }
}
