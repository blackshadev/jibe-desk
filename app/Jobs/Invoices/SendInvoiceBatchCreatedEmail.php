<?php

declare(strict_types=1);

namespace App\Jobs\Invoices;

use App\Domain\Invoices\InvoiceBatchId;
use App\Domain\Invoices\InvoiceBatchRepository;
use App\Jobs\BaseJob;
use App\Mail\Invoices\InvoiceBatchCreatedMail;
use Illuminate\Support\Facades\Mail;

final class SendInvoiceBatchCreatedEmail extends BaseJob
{
    public function __construct(
        private readonly InvoiceBatchId $invoiceBatchId,
    ) {}

    public function handle(InvoiceBatchRepository $batchRepository): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $mailData = $batchRepository->getBatchEmailData($this->invoiceBatchId);

        Mail::to(config('mail.invoicing.address'))
            ->send(new InvoiceBatchCreatedMail($mailData));
    }
}
