<?php

declare(strict_types=1);

namespace App\Domain\Invoices\Jobs;

use App\Domain\Invoices\InvoiceId;
use App\Domain\Invoices\InvoiceMailRepository;
use App\Domain\Invoices\Mails\InvoiceMail;
use App\Domain\Invoices\SepaConfiguration;
use App\Domain\Jobs\BaseJob;
use App\Domain\Mail\MailSender;
use Throwable;

final class SendInvoiceEmail extends BaseJob
{
    public function __construct(
        public readonly InvoiceId $invoiceId,
    ) {}

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [
            //            new RateLimited('invoice-emails')
        ];
    }

    /** @throws Throwable */
    public function handle(InvoiceMailRepository $repository, SepaConfiguration $configuration, MailSender $mailSender): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $mailData = $repository->getInvoiceMailData($this->invoiceId);

        $mailSender->send(new InvoiceMail($mailData, $configuration));
    }
}
