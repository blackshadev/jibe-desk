<?php

declare(strict_types=1);

namespace App\Domain\Invoices\Jobs;

use App\Domain\Invoices\SepaBatchId;
use App\Domain\Invoices\SepaBatchService;
use App\Domain\Jobs\BaseJob;

final class FinishSepaBatchGeneration extends BaseJob
{
    public function __construct(
        private readonly SepaBatchId $sepaBatchId,
    ) {}

    public function handle(SepaBatchService $batchService): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $batchService->finishGeneration($this->sepaBatchId);
    }
}
