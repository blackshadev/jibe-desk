<?php

declare(strict_types=1);

namespace App\Domain\Invoices;

use DateTimeInterface;
use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface SepaBatchRepository
{
    public function create(DateTimeInterface $invoiceDate, DateTimeInterface $sepaTransferDate, SepaBatchStatus $status): SepaBatchId;

    public function addOpenInvoicesFromBatchMonth(SepaBatchId $batchId): int;

    public function markGenerationStarted(SepaBatchId $batchId, int $expectedInvoices): void;

    public function markGenerationFinished(SepaBatchId $batchId): void;

    /** @return list<SepaExportInvoice> */
    public function getInvoicesForExport(SepaBatchId $batchId): array;

    public function markInvoicesAsPending(SepaBatchId $batchId): void;

    public function closeBatch(SepaBatchId $batchId): void;

    public function completeBatch(SepaBatchId $batchId): void;

    /** @return list<InvoiceId> */
    public function getPendingInvoicesForBatch(SepaBatchId $batchId): array;

    public function getSepaTransferDate(SepaBatchId $batchId): DateTimeInterface;

    public function getBatchEmailData(SepaBatchId $batchId): SepaBatchEmailData;

    public function attachInvoice(SepaBatchId $batchId, InvoiceId $id): void;
}
