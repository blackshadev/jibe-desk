<?php

declare(strict_types=1);

namespace App\Domain\Billing\Payment;

use App\Domain\Billing\Invoices\InvoiceId;
use App\Domain\Billing\Invoices\InvoiceIdList;
use App\Domain\Billing\Payment\Readiness\ExportConfirmation;
use App\Domain\Billing\Payment\Readiness\SepaBatchExportContext;
use App\Domain\Billing\Payment\Readiness\SepaCollectionCandidate;
use DateTimeInterface;
use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface SepaBatchRepository
{
    public function create(DateTimeInterface $invoiceDate, DateTimeInterface $sepaTransferDate, SepaBatchStatus $status): SepaBatchId;

    public function addOpenInvoicesFromBatchMonth(SepaBatchId $batchId): int;

    public function addOpenPurchaseOrdersFromBatchMonth(SepaBatchId $batchId): int;

    public function markGenerationStarted(SepaBatchId $batchId, int $expectedInvoices): void;

    public function markGenerationFinished(SepaBatchId $batchId): void;

    /** @return list<SepaExportInvoice> */
    public function getInvoicesForExport(SepaBatchId $batchId): array;

    /** @return list<SepaExportPurchaseOrder> */
    public function getPurchaseOrdersForExport(SepaBatchId $batchId): array;

    public function markInvoicesAsPending(SepaBatchId $batchId): void;

    public function closeBatch(SepaBatchId $batchId): void;

    public function completeBatch(SepaBatchId $batchId): void;

    /** @return list<InvoiceId> */
    public function getPendingInvoicesForBatch(SepaBatchId $batchId): array;

    public function getSepaTransferDate(SepaBatchId $batchId): DateTimeInterface;

    public function getBatchEmailData(SepaBatchId $batchId): SepaBatchEmailData;

    public function attachInvoice(SepaBatchId $batchId, InvoiceId $id): void;

    public function attachInvoices(SepaBatchId $batchId, InvoiceIdList $ids): int;

    /** @return list<SepaCollectionCandidate> */
    public function getCollectionCandidates(SepaBatchId $batchId): array;

    public function getExportContext(SepaBatchId $batchId): SepaBatchExportContext;

    public function recordExport(
        SepaBatchId $batchId,
        ExportConfirmation $confirmation,
        ?int $exportedByUserId,
        DateTimeInterface $exportedAt,
    ): void;

    public function markSubmitted(SepaBatchId $batchId, int $submittedByUserId, DateTimeInterface $submittedAt): void;

    /**
     * Mandates with a positive, non-credit invoice in the batch.
     *
     * @return list<PaymentInformationId>
     */
    public function getCollectedMandateIds(SepaBatchId $batchId): array;
}
