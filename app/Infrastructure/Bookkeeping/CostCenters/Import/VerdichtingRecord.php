<?php

declare(strict_types=1);

namespace App\Infrastructure\Bookkeeping\CostCenters\Import;

final readonly class VerdichtingRecord
{
    public function __construct(
        public int $lineNumber,
        public string $number,
        public string $name,
        public string $parentNumber,
        public string $parentName,
    ) {}
}
