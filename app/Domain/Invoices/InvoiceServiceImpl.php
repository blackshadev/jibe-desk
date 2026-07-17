<?php

declare(strict_types=1);

namespace App\Domain\Invoices;

use App\Domain\Bookkeeping\BookkeepingRecordRepository;
use Override;

final readonly class InvoiceServiceImpl implements InvoiceService
{
    public function __construct(
        private InvoiceRepository $invoiceRepository,
        private BookkeepingRecordRepository $bookkeepingRepository,
    ) {}

    #[Override]
    public function markAsPaid(InvoiceId $id): void
    {
        $this->invoiceRepository->markAsPaid($id);
        $this->bookkeepingRepository->createForInvoice($id);
    }
}
