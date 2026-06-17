<?php

declare(strict_types=1);

namespace App\Domain\Invoices\Listeners;

use App\Domain\Invoices\Events\InvoiceBatchClosed;
use App\Domain\Invoices\InvoiceBatchRepository;
use App\Domain\Jobs\JobDispatcher;
use App\Jobs\SendInvoiceEmail;
use Throwable;

final readonly class QueueInvoiceEmails
{
    public function __construct(
        private InvoiceBatchRepository $batchRepository,
        private JobDispatcher $dispatcher,
    ) {}

    /** @throws Throwable */
    public function handle(InvoiceBatchClosed $event): void
    {
        $invoiceIds = $this->batchRepository->getPendingInvoicesForBatch($event->batchId);

        if ($invoiceIds === []) {
            return;
        }

        $this->dispatcher->batch(
            name: 'invoice-emails-batch-' . $event->batchId->value,
            jobs: array_map(static fn ($invoiceId) => new SendInvoiceEmail($invoiceId), $invoiceIds),
        );
    }
}
