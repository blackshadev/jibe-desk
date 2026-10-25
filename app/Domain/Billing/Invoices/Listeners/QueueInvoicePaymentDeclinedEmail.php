<?php

declare(strict_types=1);

namespace App\Domain\Billing\Invoices\Listeners;

use App\Domain\Billing\Invoices\Events\InvoicePaymentDeclined;
use App\Domain\Billing\Invoices\InvoiceMailRepository;
use App\Domain\Billing\Invoices\InvoicePaymentDeclinedMailUnavailableException;
use App\Domain\Billing\Invoices\Jobs\SendInvoicePaymentDeclinedEmail;
use App\Domain\Jobs\JobDispatcher;

final readonly class QueueInvoicePaymentDeclinedEmail
{
    public function __construct(
        private InvoiceMailRepository $mailRepository,
        private JobDispatcher $dispatcher,
    ) {}

    public function handle(InvoicePaymentDeclined $event): void
    {
        try {
            $this->mailRepository->getInvoicePaymentDeclinedMailData($event->invoiceId);
        } catch (InvoicePaymentDeclinedMailUnavailableException) {
            return;
        }

        $this->dispatcher->dispatch(new SendInvoicePaymentDeclinedEmail($event->invoiceId));
    }
}
