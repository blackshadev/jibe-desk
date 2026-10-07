<?php

declare(strict_types=1);

namespace App\Domain\Invoices;

use App\Domain\Bookkeeping\BookkeepingRecordRepository;
use App\Domain\Invoices\Events\InvoiceBatchClosed;
use DateTimeInterface;
use Illuminate\Contracts\Events\Dispatcher;
use Override;

final readonly class InvoiceBatchServiceImpl implements InvoiceBatchService
{
    public function __construct(
        private InvoiceBatchRepository $batchRepository,
        private BookkeepingRecordRepository $bookkeepingRepository,
        private Dispatcher $eventDispatcher,
    ) {}

    #[Override]
    public function createBatch(DateTimeInterface $invoiceDate, DateTimeInterface $sepaTransferDate): InvoiceBatchId
    {
        return $this->batchRepository->create($invoiceDate, $sepaTransferDate, InvoiceBatchStatus::Open);
    }

    #[Override]
    public function attachBatchMonth(InvoiceBatchId $batchId): int
    {
        return $this->batchRepository->addOpenInvoicesFromBatchMonth($batchId);
    }

    #[Override]
    public function startGeneration(InvoiceBatchId $batchId, int $expectedInvoices): void
    {
        $this->batchRepository->markGenerationStarted($batchId, $expectedInvoices);
    }

    #[Override]
    public function finishGeneration(InvoiceBatchId $batchId): void
    {
        $this->batchRepository->markGenerationFinished($batchId);
    }

    #[Override]
    public function closeBatch(InvoiceBatchId $batchId): void
    {
        $this->batchRepository->markInvoicesAsPending($batchId);
        $this->bookkeepingRepository->createForBatch($batchId);
        $this->batchRepository->closeBatch($batchId);

        $this->eventDispatcher->dispatch(new InvoiceBatchClosed(batchId: $batchId));
    }

    /**
     * @throws \DomainException
     */
    #[Override]
    public function completeBatch(InvoiceBatchId $batchId): void
    {
        $this->batchRepository->completeBatch($batchId);
    }
}
