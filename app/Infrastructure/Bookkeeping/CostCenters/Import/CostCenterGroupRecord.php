<?php

declare(strict_types=1);

namespace App\Infrastructure\Bookkeeping\CostCenters\Import;

final readonly class CostCenterGroupRecord
{
    public function __construct(
        public string $number,
        public string $externalNumber,
        public string $name,
        public ?string $parentNumber,
    ) {}
}
