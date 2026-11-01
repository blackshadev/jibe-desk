<?php

declare(strict_types=1);

namespace App\Infrastructure\Bookkeeping\CostCenters\Import;

final readonly class GrootboekRecord
{
    public function __construct(
        public int $lineNumber,
        public string $accountNumber,
        public string $title,
        public string $verdichtingNumber,
        public string $verdichtingName,
    ) {}
}
