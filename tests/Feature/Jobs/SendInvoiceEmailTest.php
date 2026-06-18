<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Domain\Invoices\InvoiceId;
use App\Jobs\Invoices\SendInvoiceEmail;
use App\Mail\Invoices\InvoiceMail;
use App\Models\Invoice;
use App\Models\Member;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Mail;
use Tests\FeatureTestCase;

final class SendInvoiceEmailTest extends FeatureTestCase
{
    public function test_it_sends_invoice_email(): void
    {
        Mail::fake();

        $member = Member::factory()->createQuietly();
        $invoice = Invoice::factory()
            ->forMember($member)
            ->withLines()
            ->createQuietly();

        SendInvoiceEmail::dispatch(InvoiceId::create($invoice->id));

        Mail::assertQueued(
            InvoiceMail::class,
            static fn (InvoiceMail $mail): bool => $mail->hasTo($member->email) && $mail->hasSubject('Factuur ' . $invoice->invoice_number),
        );
    }

    public function test_it_does_not_send_when_invoice_not_found(): void
    {
        Mail::fake();

        $this->expectException(ModelNotFoundException::class);

        SendInvoiceEmail::dispatch(InvoiceId::create(999_999));
    }
}
