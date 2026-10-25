<?php

declare(strict_types=1);

namespace App\Domain\Billing\Invoices\Jobs;

use App\Domain\Billing\Invoices\InvoiceId;
use App\Domain\Billing\Invoices\InvoiceMailRepository;
use App\Domain\Billing\Invoices\InvoicePaymentDeclinedMailUnavailableException;
use App\Domain\Billing\Invoices\Mails\InvoicePaymentDeclinedMail;
use App\Domain\Billing\Payment\SepaConfiguration;
use App\Domain\Jobs\BaseJob;
use App\Domain\Mail\MailSender;

final class SendInvoicePaymentDeclinedEmail extends BaseJob
{
    public function __construct(
        public readonly InvoiceId $invoiceId,
    ) {}

    public function handle(
        InvoiceMailRepository $repository,
        SepaConfiguration $configuration,
        MailSender $mailSender,
    ): void {
        if ($this->batch()?->cancelled()) {
            return;
        }

        try {
            $mailData = $repository->getInvoicePaymentDeclinedMailData($this->invoiceId);
        } catch (InvoicePaymentDeclinedMailUnavailableException) {
            return;
        }

        $mailSender->send(new InvoicePaymentDeclinedMail($mailData, $configuration));
    }
}
