<?php

declare(strict_types=1);

namespace App\Domain\Billing\Payment;

use App\Domain\Billing\Invoices\InvoiceIdList;
use App\Domain\Billing\Payment\Events\SepaBatchClosed;
use App\Domain\Billing\Payment\Readiness\ExportConfirmation;
use App\Domain\Billing\Payment\Readiness\ExportNotReadyException;
use App\Domain\Billing\Payment\Readiness\ExportReadinessService;
use App\Domain\Bookkeeping\BookkeepingRecords\BookkeepingRecordRepository;
use DateTimeInterface;
use DomainException;
use Illuminate\Contracts\Events\Dispatcher;
use Override;
use Psr\Clock\ClockInterface;

final readonly class SepaBatchServiceImpl implements SepaBatchService
{
    public function __construct(
        private SepaBatchRepository $batchRepository,
        private BookkeepingRecordRepository $bookkeepingRepository,
        private Dispatcher $eventDispatcher,
        private SepaExportService $exportService,
        private ExportReadinessService $readinessService,
        private ClockInterface $clock,
        private MandateRepository $mandateRepository,
    ) {}

    #[Override]
    public function createBatch(DateTimeInterface $invoiceDate, DateTimeInterface $sepaTransferDate): SepaBatchId
    {
        return $this->batchRepository->create($invoiceDate, $sepaTransferDate, SepaBatchStatus::Open);
    }

    #[Override]
    public function attachBatchMonthInvoices(SepaBatchId $batchId): int
    {
        return $this->batchRepository->addOpenInvoicesFromBatchMonth($batchId);
    }

    #[Override]
    public function attachBatchMonthPurchaseOrders(SepaBatchId $batchId): int
    {
        return $this->batchRepository->addOpenPurchaseOrdersFromBatchMonth($batchId);
    }

    #[Override]
    public function startGeneration(SepaBatchId $batchId, int $expectedInvoices): void
    {
        $this->batchRepository->markGenerationStarted($batchId, $expectedInvoices);
    }

    #[Override]
    public function finishGeneration(SepaBatchId $batchId): void
    {
        $this->batchRepository->markGenerationFinished($batchId);
    }

    #[Override]
    public function closeBatch(SepaBatchId $batchId): void
    {
        $this->batchRepository->markInvoicesAsPending($batchId);
        $this->batchRepository->freezeSequenceTypes($batchId);
        $this->bookkeepingRepository->createForBatch($batchId);
        $this->batchRepository->settleCreditPairs($batchId);
        $this->batchRepository->closeBatch($batchId);

        $this->eventDispatcher->dispatch(new SepaBatchClosed(batchId: $batchId));
    }

    /**
     * @throws \DomainException
     */
    #[Override]
    public function completeBatch(SepaBatchId $batchId): void
    {
        $this->batchRepository->completeBatch($batchId);
    }

    #[Override]
    public function attachInvoices(SepaBatchId $batchId, InvoiceIdList $ids): int
    {
        return $this->batchRepository->attachInvoices($batchId, $ids);
    }

    /**
     * @throws DomainException
     * @throws ExportNotReadyException
     */
    #[Override]
    public function export(SepaBatchId $batchId, ExportConfirmation $confirmation, ?int $exportedByUserId): SepaExport
    {
        if ($this->batchRepository->getExportContext($batchId)->status !== SepaBatchStatus::Pending) {
            throw new DomainException('Only a pending batch can be exported.');
        }

        $report = $this->readinessService->assess($batchId);

        if (!$report->isSatisfiedBy($confirmation)) {
            throw new ExportNotReadyException($report);
        }

        $this->batchRepository->freezeSequenceTypes($batchId);

        $export = $this->exportService->export($batchId);

        $this->batchRepository->recordExport($batchId, $confirmation, $exportedByUserId, $this->clock->now());

        return $export;
    }

    #[Override]
    public function markSubmitted(SepaBatchId $batchId, int $submittedByUserId): void
    {
        $submittedAt = $this->clock->now();

        $this->batchRepository->markSubmitted($batchId, $submittedByUserId, $submittedAt);

        $this->mandateRepository->stampCollectionsForBatch($batchId, $submittedAt);
    }

    #[Override]
    public function replanSequenceTypes(SepaBatchId $batchId): void
    {
        $this->batchRepository->replanSequenceTypes($batchId);
    }
}
