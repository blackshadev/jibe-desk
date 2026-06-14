<?php

declare(strict_types=1);

namespace App\Domain\Invoices;

use DateTimeInterface;

final readonly class InvoiceBatchService
{
    public function __construct(
        private InvoiceBatchRepository $batchRepository,
    ) {}

    public function createBatch(DateTimeInterface $invoiceDate): InvoiceBatchId
    {
        return $this->batchRepository->create($invoiceDate, InvoiceBatchStatus::Open);
    }

    public function attachBatchMonth(InvoiceBatchId $batchId): void
    {
        $this->batchRepository->addOpenInvoicesFromBatchMonth($batchId);
    }

    public function closeBatch(InvoiceBatchId $batchId): void
    {
        $this->batchRepository->markInvoicesAsPending($batchId);
        $this->batchRepository->closeBatch($batchId);
    }

    /**
     * @throws \DomainException
     */
    public function completeBatch(InvoiceBatchId $batchId): void
    {
        $this->batchRepository->completeBatch($batchId);
    }
}
