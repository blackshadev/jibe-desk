<?php

declare(strict_types=1);

namespace App\Domain\Billing\Invoices;

use App\Domain\Billing\Invoices\Events\InvoicePaymentDeclined;
use App\Domain\Billing\Invoices\Jobs\SendInvoiceEmail;
use App\Domain\Bookkeeping\Banking\BankTransactions\BankTransactionId;
use App\Domain\Bookkeeping\BookkeepingRecords\BookkeepingRecordRepository;
use App\Domain\Jobs\JobDispatcher;
use Illuminate\Contracts\Events\Dispatcher;
use Override;

final readonly class InvoiceServiceImpl implements InvoiceService
{
    public function __construct(
        private InvoiceRepository $invoiceRepository,
        private BookkeepingRecordRepository $bookkeepingRepository,
        private JobDispatcher $jobDispatcher,
        private Dispatcher $eventDispatcher,
    ) {}

    #[Override]
    public function createCredit(InvoiceId $originalInvoiceId): InvoiceId
    {
        return $this->invoiceRepository->createCredit($originalInvoiceId);
    }

    #[Override]
    public function markAsPaid(InvoiceIdList $ids): void
    {
        $this->invoiceRepository->markAsPaid($ids);
        $this->bookkeepingRepository->createForInvoice($ids);
    }

    #[Override]
    public function markAsDeclined(
        InvoiceIdList $ids,
        ?BankTransactionId $reversalTransactionId = null,
        ?string $note = null,
    ): void {
        $declinedIds = $this->invoiceRepository->markAsDeclined($ids, $reversalTransactionId, $note);

        foreach ($declinedIds->ids as $invoiceId) {
            $this->eventDispatcher->dispatch(new InvoicePaymentDeclined($invoiceId));
        }
    }

    #[Override]
    public function reopen(InvoiceIdList $ids): void
    {
        $this->invoiceRepository->reopen($ids);
    }

    #[Override]
    public function restoreDeclined(InvoiceIdList $ids): void
    {
        $this->invoiceRepository->markAsPendingIfDeclined($ids);
        $this->bookkeepingRepository->createForInvoice($ids);
    }

    #[Override]
    public function markAsPending(InvoiceIdList $ids): void
    {
        $this->invoiceRepository->markAsPending($ids);
        $this->bookkeepingRepository->createForInvoice($ids);

        foreach ($ids->ids as $id) {
            $this->jobDispatcher->dispatch(new SendInvoiceEmail(new SendInvoiceEmailData($id)));
        }
    }
}
