<?php

declare(strict_types=1);

namespace App\Domain\Billing\Invoices\Listeners;

use App\Domain\Billing\Invoices\InvoiceId;
use App\Domain\Billing\Invoices\Jobs\SendInvoiceEmail;
use App\Domain\Billing\Invoices\SendInvoiceEmailData;
use App\Domain\Billing\Payment\Events\SepaBatchClosed;
use App\Domain\Billing\Payment\SepaBatchRepository;
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
