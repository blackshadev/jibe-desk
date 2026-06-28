<?php

declare(strict_types=1);

namespace App\Domain\Invoices\Jobs;

use App\Domain\Invoices\InvoiceBatchId;
use App\Domain\Invoices\InvoiceBatchRepository;
use App\Domain\Invoices\Mails\InvoiceBatchCreatedMail;
use App\Domain\Jobs\BaseJob;
use App\Domain\Mail\FinancialAdministrationRecipient;
use App\Domain\Mail\MailSender;

final class SendInvoiceBatchCreatedEmail extends BaseJob
{
    public function __construct(
        private readonly InvoiceBatchId $invoiceBatchId,
    ) {}

    public function handle(InvoiceBatchRepository $batchRepository, MailSender $mailSender, FinancialAdministrationRecipient $admin): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $mailData = $batchRepository->getBatchEmailData($this->invoiceBatchId);

        $mailSender->send(new InvoiceBatchCreatedMail($mailData, $admin->recipient));
    }
}
