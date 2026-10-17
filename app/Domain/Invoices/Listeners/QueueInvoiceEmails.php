<?php

declare(strict_types=1);

namespace App\Domain\Invoices\Listeners;

use App\Domain\Invoices\Events\SepaBatchClosed;
use App\Domain\Invoices\InvoiceId;
use App\Domain\Invoices\Jobs\SendInvoiceEmail;
use App\Domain\Invoices\SendInvoiceEmailData;
use App\Domain\Invoices\SepaBatchRepository;
use App\Domain\Jobs\JobBatch;
use App\Domain\Jobs\JobDispatcher;
use Throwable;

final readonly class QueueInvoiceEmails
{
    public function __construct(
        private SepaBatchRepository $batchRepository,
        private JobDispatcher $dispatcher,
    ) {}

    /** @throws Throwable */
    public function handle(SepaBatchClosed $event): void
    {
        $invoiceIds = $this->batchRepository->getPendingInvoicesForBatch($event->batchId);

        if ($invoiceIds === []) {
            return;
        }

        $this->dispatcher->dispatch(
            new JobBatch(
                'invoice-emails-batch-' . $event->batchId->value,
                array_map(static fn (InvoiceId $invoiceId) => new SendInvoiceEmail(new SendInvoiceEmailData($invoiceId)), $invoiceIds),
            ),
        );
    }
}
