<?php

declare(strict_types=1);

namespace App\Infrastructure\Bookkeeping\CostCenters\Import;

final readonly class HoofdverdichtingRecord
{
    public function __construct(
        public int $lineNumber,
        public string $number,
        public string $name,
    ) {}
}
