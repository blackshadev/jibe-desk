<?php

declare(strict_types=1);

namespace App\Domain\Invoices\Jobs;

use App\Domain\Invoices\InvoiceBatchId;
use App\Domain\Invoices\InvoiceBatchService;
use App\Domain\Jobs\BaseJob;

final class FinishInvoiceBatchGeneration extends BaseJob
{
    public function __construct(
        private readonly InvoiceBatchId $invoiceBatchId,
    ) {}

    public function handle(InvoiceBatchService $batchService): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $batchService->finishGeneration($this->invoiceBatchId);
    }
}
