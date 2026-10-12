<?php

declare(strict_types=1);

namespace App\Domain\Bookkeeping;

use App\Domain\SharedKernel\CompoundPrice;

final readonly class CostCenterYearResult
{
    public function __construct(
        public CostCenterId $costCenterId,
        public string $number,
        public string $title,
        public float $startingAmount,
        public CompoundPrice $totalBookkeeping,
    ) {}

    public function result(): CompoundPrice
    {
        return $this->totalBookkeeping->add(
            new CompoundPrice($this->startingAmount, 0.0),
        );
    }
}
