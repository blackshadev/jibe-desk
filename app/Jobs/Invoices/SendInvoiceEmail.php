<?php

declare(strict_types=1);

namespace App\Jobs\Invoices;

use App\Domain\Invoices\InvoiceId;
use App\Domain\Invoices\InvoiceMailRepository;
use App\Infrastructure\Invoices\SepaConfiguration;
use App\Jobs\BaseJob;
use App\Mail\Invoices\InvoiceMail;
use Illuminate\Support\Facades\Mail;
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
    public function handle(InvoiceMailRepository $repository, SepaConfiguration $configuration): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $mailData = $repository->getInvoiceMailData($this->invoiceId);

        Mail::to($mailData->recipient->email, $mailData->recipient->name)
            ->send(new InvoiceMail($mailData, $configuration));
    }
}
