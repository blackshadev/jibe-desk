<?php

declare(strict_types=1);

namespace App\Domain\Billing\Payment\Jobs;

use App\Domain\Billing\Payment\SepaBatchId;
use App\Domain\Billing\Payment\SepaBatchService;
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
