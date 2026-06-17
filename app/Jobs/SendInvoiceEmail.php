<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Invoices\InvoiceId;
use App\Domain\Invoices\InvoiceMailRepository;
use App\Domain\Jobs\Job;
use App\Mail\InvoiceMail;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class SendInvoiceEmail implements Job
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use Batchable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly InvoiceId $invoiceId,
    ) {}

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [new RateLimited('invoice-emails')];
    }

    /** @throws Throwable */
    public function handle(InvoiceMailRepository $repository): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $mailData = $repository->getInvoiceMailData($this->invoiceId);

        Mail::to($mailData->memberEmail, $mailData->memberName)
            ->send(new InvoiceMail($mailData));
    }
}
