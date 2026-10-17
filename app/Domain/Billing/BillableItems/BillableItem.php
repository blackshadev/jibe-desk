<?php

declare(strict_types=1);

namespace App\Domain\Billing\BillableItems;

use App\Domain\Bookkeeping\CostCenters\CostCenterId;
use App\Domain\SharedKernel\CompoundPrice;

final readonly class BillableItem
{
    public function __construct(
        public BillableItemId $id,
        public CompoundPrice $price,
        public float $quantity,
        public string $description,
        public CostCenterId $costCenterId,
    ) {}
}
