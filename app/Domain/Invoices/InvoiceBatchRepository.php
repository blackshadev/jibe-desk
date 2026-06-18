<?php

declare(strict_types=1);

namespace App\Domain\Invoices;

use DateTimeInterface;
use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface InvoiceBatchRepository
{
    public function create(DateTimeInterface $invoiceDate, InvoiceBatchStatus $status): InvoiceBatchId;

    public function addOpenInvoicesFromBatchMonth(InvoiceBatchId $batchId): void;

    /** @return list<SepaExportInvoice> */
    public function getInvoicesForExport(InvoiceBatchId $batchId): array;

    public function markInvoicesAsPending(InvoiceBatchId $batchId): void;

    public function closeBatch(InvoiceBatchId $batchId): void;

    public function completeBatch(InvoiceBatchId $batchId): void;

    /** @return list<InvoiceId> */
    public function getPendingInvoicesForBatch(InvoiceBatchId $batchId): array;

    public function getBatchDate(InvoiceBatchId $batchId): DateTimeInterface;

    public function getBatchEmailData(InvoiceBatchId $batchId): InvoiceBatchEmailData;

    public function attachInvoice(InvoiceBatchId $batchId, InvoiceId $id): void;
}
