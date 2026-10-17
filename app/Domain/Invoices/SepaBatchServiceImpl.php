<?php

declare(strict_types=1);

namespace App\Domain\Invoices;

use App\Domain\Bookkeeping\BookkeepingRecordRepository;
use App\Domain\Invoices\Events\SepaBatchClosed;
use DateTimeInterface;
use Illuminate\Contracts\Events\Dispatcher;
use Override;

final readonly class SepaBatchServiceImpl implements SepaBatchService
{
    public function __construct(
        private SepaBatchRepository $batchRepository,
        private BookkeepingRecordRepository $bookkeepingRepository,
        private Dispatcher $eventDispatcher,
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
        $this->bookkeepingRepository->createForBatch($batchId);
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
}
