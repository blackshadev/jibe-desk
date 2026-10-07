<?php

declare(strict_types=1);

namespace App\Domain\Invoices;

use DateTimeInterface;
use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface InvoiceBatchService
{
    public function createBatch(DateTimeInterface $invoiceDate, DateTimeInterface $sepaTransferDate): InvoiceBatchId;

    public function attachBatchMonth(InvoiceBatchId $batchId): int;

    public function startGeneration(InvoiceBatchId $batchId, int $expectedInvoices): void;

    public function finishGeneration(InvoiceBatchId $batchId): void;

    public function closeBatch(InvoiceBatchId $batchId): void;

    public function completeBatch(InvoiceBatchId $batchId): void;
}
