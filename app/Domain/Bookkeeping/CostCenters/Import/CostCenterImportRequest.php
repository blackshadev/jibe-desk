<?php

declare(strict_types=1);

namespace App\Domain\Bookkeeping\CostCenters\Import;

final readonly class CostCenterImportRequest
{
    public function __construct(
        public string $grootboekPath,
        public string $verdichtingenPath,
        public string $hoofdverdichtingenPath,
        public bool $dryRun,
        public bool $validateOnly,
    ) {}
}
