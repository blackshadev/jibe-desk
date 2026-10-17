<?php

declare(strict_types=1);

namespace App\Domain\Invoices;

use DateTimeInterface;
use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface SepaBatchService
{
    public function createBatch(DateTimeInterface $invoiceDate, DateTimeInterface $sepaTransferDate): SepaBatchId;

    public function attachBatchMonthInvoices(SepaBatchId $batchId): int;

    public function attachBatchMonthPurchaseOrders(SepaBatchId $batchId): int;

    public function startGeneration(SepaBatchId $batchId, int $expectedInvoices): void;

    public function finishGeneration(SepaBatchId $batchId): void;

    public function closeBatch(SepaBatchId $batchId): void;

    public function completeBatch(SepaBatchId $batchId): void;
}
