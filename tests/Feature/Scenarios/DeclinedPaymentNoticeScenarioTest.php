<?php

declare(strict_types=1);

namespace Tests\Feature\Scenarios;

use App\Domain\Billing\Invoices\InvoiceId;
use App\Domain\Billing\Invoices\InvoiceIdList;
use App\Domain\Billing\Invoices\InvoiceService;
use App\Domain\Billing\Invoices\InvoiceStatus;
use App\Domain\Billing\Invoices\Mails\InvoicePaymentDeclinedMail;
use App\Domain\Bookkeeping\Banking\BankTransactions\BankTransactionId;
use App\Domain\Mail\OutgoingEmailStatus;
use App\Infrastructure\Mail\MailMailable;
use App\Models\BankTransaction;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\PaymentInformation;
use Illuminate\Support\Facades\Mail;
use Tests\FeatureTestCase;

final class DeclinedPaymentNoticeScenarioTest extends FeatureTestCase
{
    public function test_automatic_reversal_decline_queues_the_declined_notice(): void
    {
        $member = Member::factory()->has(PaymentInformation::factory())->createOneQuietly([
            'email' => 'gezin@example.com',
        ]);

        $invoice = Invoice::factory()
            ->forMember($member)
            ->createQuietly([
                'status' => InvoiceStatus::Pending,
                'invoice_number' => 'I-20261001',
            ]);

        $reversal = BankTransaction::factory()->createQuietly();

        Mail::fake();

        $this->app->make(InvoiceService::class)->markAsDeclined(
            new InvoiceIdList([InvoiceId::create($invoice->id)]),
            BankTransactionId::create($reversal->id),
        );

        Mail::assertQueued(
            MailMailable::class,
            static fn (MailMailable $mail): bool => $mail->hasTo('gezin@example.com'),
        );
    }

    public function test_manual_decline_queues_the_declined_notice(): void
    {
        $member = Member::factory()->has(PaymentInformation::factory())->createOneQuietly();

        $invoice = Invoice::factory()
            ->forMember($member)
            ->createQuietly([
                'status' => InvoiceStatus::Pending,
                'invoice_number' => 'I-20261001',
                'recipient_email' => 'gezin@example.com',
            ]);

        Mail::fake();

        $this->app->make(InvoiceService::class)->markAsDeclined(
            new InvoiceIdList([InvoiceId::create($invoice->id)]),
            null,
            'Handmatig geweigerd',
        );

        Mail::assertQueued(
            MailMailable::class,
            static fn (MailMailable $mail): bool => $mail->hasTo('gezin@example.com'),
        );
    }

    public function test_manual_decline_sends_no_notice_for_a_credit_invoice(): void
    {
        $member = Member::factory()->has(PaymentInformation::factory())->createOneQuietly();

        $original = Invoice::factory()
            ->forMember($member)
            ->createQuietly([
                'status' => InvoiceStatus::Paid,
            ]);

        $credit = Invoice::factory()
            ->forMember($member)
            ->createQuietly([
                'status' => InvoiceStatus::Pending,
                'credit_invoice_id' => $original->id,
            ]);

        Mail::fake();

        $this->app
            ->make(InvoiceService::class)
            ->markAsDeclined(
                new InvoiceIdList([InvoiceId::create($credit->id)]),
            );

        Mail::assertNothingQueued();
    }

    public function test_manual_decline_does_not_send_a_notice_without_a_recipient_email(): void
    {
        $member = Member::factory()->has(PaymentInformation::factory())->createOneQuietly([
            'email' => '',
        ]);

        $invoice = Invoice::factory()
            ->forMember($member)
            ->createQuietly([
                'status' => InvoiceStatus::Pending,
            ]);

        Mail::fake();

        $this->app
            ->make(InvoiceService::class)
            ->markAsDeclined(
                new InvoiceIdList([InvoiceId::create($invoice->id)]),
            );

        Mail::assertNothingQueued();
    }

    public function test_declining_an_already_declined_invoice_does_not_send_a_second_notice(): void
    {
        $member = Member::factory()->has(PaymentInformation::factory())->createOneQuietly([
            'email' => 'gezin@example.com',
        ]);

        $invoice = Invoice::factory()
            ->forMember($member)
            ->createQuietly([
                'status' => InvoiceStatus::Pending,
            ]);

        $ids = new InvoiceIdList([InvoiceId::create($invoice->id)]);
        $service = $this->app->make(InvoiceService::class);

        Mail::fake();

        $service->markAsDeclined($ids);
        $service->markAsDeclined($ids);

        Mail::assertQueued(MailMailable::class);
        static::assertCount(1, Mail::queued(MailMailable::class));
    }

    public function test_manual_decline_records_the_notice_in_the_outgoing_email_log(): void
    {
        $member = Member::factory()->has(PaymentInformation::factory())->createOneQuietly([
            'email' => 'gezin@example.com',
        ]);

        $invoice = Invoice::factory()
            ->forMember($member)
            ->createQuietly([
                'status' => InvoiceStatus::Pending,
                'invoice_number' => 'I-20261001',
            ]);

        $this->app
            ->make(InvoiceService::class)
            ->markAsDeclined(
                new InvoiceIdList([InvoiceId::create($invoice->id)]),
            );

        $this->assertDatabaseHas('outgoing_emails', [
            'mailable_class' => InvoicePaymentDeclinedMail::class,
            'recipient_email' => 'gezin@example.com',
            'subject' => 'Betaling van factuur I-20261001 is niet gelukt',
            'member_id' => $member->id,
            'status' => OutgoingEmailStatus::Sent->value,
        ]);
    }
}
