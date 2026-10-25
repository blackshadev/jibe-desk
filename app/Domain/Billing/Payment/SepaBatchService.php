<?php

declare(strict_types=1);

namespace App\Domain\Billing\Payment;

use App\Domain\Billing\Invoices\InvoiceIdList;
use App\Domain\Billing\Payment\Readiness\ExportConfirmation;
use App\Domain\Billing\Payment\Readiness\ExportNotReadyException;
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

    public function attachInvoices(SepaBatchId $batchId, InvoiceIdList $ids): int;

    /**
     * @throws ExportNotReadyException
     */
    public function export(SepaBatchId $batchId, ExportConfirmation $confirmation, ?int $exportedByUserId): SepaExport;

    public function markSubmitted(SepaBatchId $batchId, int $submittedByUserId): void;
}
