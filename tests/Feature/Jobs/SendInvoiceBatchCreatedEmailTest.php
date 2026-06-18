<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Domain\Invoices\InvoiceBatchId;
use App\Jobs\Invoices\SendInvoiceBatchCreatedEmail;
use App\Mail\Invoices\InvoiceBatchCreatedMail;
use App\Models\Invoice;
use App\Models\InvoiceBatch;
use App\Models\InvoiceLine;
use Illuminate\Support\Facades\Mail;
use Tests\FeatureTestCase;

final class SendInvoiceBatchCreatedEmailTest extends FeatureTestCase
{
    public function test_it_sends_invoice_batch_created_email(): void
    {
        Mail::fake();

        $batch = InvoiceBatch::factory()->create(['invoice_date' => '2026-06-15']);
        $invoice = Invoice::factory()->forBatch($batch)->createQuietly();
        InvoiceLine::factory()
            ->state(['invoice_id' => $invoice->id])
            ->createQuietly(['price' => 25.00, 'quantity' => 2, 'vat' => 10.50]);

        SendInvoiceBatchCreatedEmail::dispatch(InvoiceBatchId::create($batch->id));

        Mail::assertQueued(
            InvoiceBatchCreatedMail::class,
            static fn (InvoiceBatchCreatedMail $mail): bool => (
                $mail->hasTo(config('mail.invoicing.address'))
                && $mail->hasSubject('Nieuwe facturatieronde aangemaakt')
                && $mail->batch->id->value === $batch->id
                && $mail->batch->invoiceCount === 1
                && $mail->batch->total->price === 50.0
            ),
        );
    }
}
