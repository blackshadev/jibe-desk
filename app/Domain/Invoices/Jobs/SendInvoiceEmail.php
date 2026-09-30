<?php

declare(strict_types=1);

namespace App\Domain\Invoices\Jobs;

use App\Domain\Invoices\InvoiceMailRepository;
use App\Domain\Invoices\Mails\InvoiceMail;
use App\Domain\Invoices\SendInvoiceEmailData;
use App\Domain\Invoices\SepaConfiguration;
use App\Domain\Jobs\BaseJob;
use App\Domain\Mail\MailSender;
use Throwable;

final class SendInvoiceEmail extends BaseJob
{
    public function __construct(
        public readonly SendInvoiceEmailData $data,
    ) {}

    /** @throws Throwable */
    public function handle(InvoiceMailRepository $repository, SepaConfiguration $configuration, MailSender $mailSender): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $mailData = $repository->getInvoiceMailData($this->data->invoiceId);

        if (! $this->data->isResend && $mailData->sentAt !== null) {
            return;
        }

        $mailSender->send(new InvoiceMail($mailData, $configuration));

        $repository->markInvoiceAsSent($this->data->invoiceId);
    }
}
