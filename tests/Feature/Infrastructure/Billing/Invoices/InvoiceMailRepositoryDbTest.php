<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Billing\Invoices;

use App\Domain\Billing\Invoices\InvoiceId;
use App\Domain\Billing\Invoices\InvoicePaymentDeclinedMailUnavailableException;
use App\Domain\Billing\Invoices\InvoicePaymentDeclinedMailUnavailableReason;
use App\Domain\Billing\Invoices\InvoiceStatus;
use App\Infrastructure\Billing\Invoices\InvoiceMailRepositoryDb;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Member;
use App\Models\PaymentInformation;
use App\Models\SepaBatch;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Override;
use Tests\FeatureTestCase;

final class InvoiceMailRepositoryDbTest extends FeatureTestCase
{
    private InvoiceMailRepositoryDb $repository;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new InvoiceMailRepositoryDb();
    }

    public function test_get_invoice_mail_data_returns_full_data(): void
    {
        $member = Member::factory()->createQuietly();
        PaymentInformation::factory()->for($member)->createQuietly([
            'mandate_accepted_date' => '2025-01-15',
        ]);

        $batch = SepaBatch::factory()->createQuietly([
            'invoice_date' => '2026-06-30',
            'sepa_transfer_date' => '2026-07-15',
        ]);

        $invoice = Invoice::factory()
            ->forMember($member)
            ->forBatch($batch)
            ->createQuietly([
                'date' => '2026-06-01',
                'invoice_number' => 'I-20260001',
                'recipient_name' => 'Test Lid',
                'recipient_address' => 'Teststraat 1\n1234 AB Amsterdam',
                'recipient_email' => 'test@example.com',
            ]);

        InvoiceLine::factory()->for($invoice)->createQuietly([
            'description' => 'Lidmaatschap',
            'price' => 25.00,
            'quantity' => 2,
            'vat' => 5.25,
        ]);

        InvoiceLine::factory()->for($invoice)->createQuietly([
            'description' => 'Activiteit',
            'price' => 10.00,
            'quantity' => 1,
            'vat' => 2.10,
        ]);

        $result = $this->repository->getInvoiceMailData(InvoiceId::create($invoice->id));

        static::assertSame($invoice->id, $result->invoiceId);
        static::assertSame('I-20260001', $result->invoiceNumber);
        static::assertSame('Test Lid', $result->recipient->name);
        static::assertSame('test@example.com', $result->recipient->email);
        static::assertStringContainsString('Teststraat 1', $result->recipientAddress);
        static::assertStringContainsString('1234 AB Amsterdam', $result->recipientAddress);
        static::assertSame('2026-06-01', $result->invoiceDate->format('Y-m-d'));
        static::assertCount(2, $result->lines);

        static::assertSame('Lidmaatschap', $result->lines[0]->description);
        static::assertSame(2.0, $result->lines[0]->quantity);
        static::assertSame(25.00, $result->lines[0]->price->price);
        static::assertSame(5.25, $result->lines[0]->price->vat);
        static::assertSame(50.00, $result->lines[0]->subTotal->price);
        static::assertSame(10.50, $result->lines[0]->subTotal->vat);

        static::assertSame('Activiteit', $result->lines[1]->description);
        static::assertSame(1.0, $result->lines[1]->quantity);
        static::assertSame(10.00, $result->lines[1]->price->price);
        static::assertSame(2.10, $result->lines[1]->price->vat);
        static::assertSame(10.00, $result->lines[1]->subTotal->price);
        static::assertSame(2.10, $result->lines[1]->subTotal->vat);

        static::assertSame(60.00, $result->total->price);
        static::assertSame(12.60, $result->total->vat);
        static::assertNull($result->sentAt);
    }

    public function test_get_invoice_mail_data_returns_sent_at_when_invoice_was_sent(): void
    {
        $invoice = Invoice::factory()->createQuietly([
            'sent_at' => '2026-05-26 09:30:00',
        ]);

        $result = $this->repository->getInvoiceMailData(InvoiceId::create($invoice->id));

        static::assertNotNull($result->sentAt);
        static::assertSame('2026-05-26 09:30:00', $result->sentAt->format('Y-m-d H:i:s'));
    }

    public function test_get_invoice_mail_data_sets_sepa_transfer_date_when_mandate_and_batch_present(): void
    {
        $member = Member::factory()->createQuietly();
        PaymentInformation::factory()->for($member)->createQuietly([
            'mandate_accepted_date' => '2025-01-15',
        ]);

        $batch = SepaBatch::factory()->createQuietly([
            'invoice_date' => '2026-06-30',
            'sepa_transfer_date' => '2026-07-15',
        ]);

        $invoice = Invoice::factory()
            ->forMember($member)
            ->forBatch($batch)
            ->createQuietly();

        $result = $this->repository->getInvoiceMailData(InvoiceId::create($invoice->id));

        static::assertNotNull($result->sepaTransferDate);
        static::assertSame('2026-07-15', $result->sepaTransferDate->format('Y-m-d'));
    }

    public function test_get_invoice_mail_data_sepa_transfer_date_null_when_no_payment_information(): void
    {
        $member = Member::factory()->createQuietly();

        $batch = SepaBatch::factory()->createQuietly([
            'invoice_date' => '2026-07-01',
            'sepa_transfer_date' => '2026-07-15',
        ]);

        $invoice = Invoice::factory()
            ->forMember($member)
            ->forBatch($batch)
            ->createQuietly();

        $result = $this->repository->getInvoiceMailData(InvoiceId::create($invoice->id));

        static::assertNull($result->sepaTransferDate);
    }

    public function test_get_invoice_mail_data_sepa_transfer_date_null_when_no_sepa_batch(): void
    {
        $member = Member::factory()->createQuietly();
        PaymentInformation::factory()->for($member)->createQuietly([
            'mandate_accepted_date' => '2025-01-15',
        ]);

        $invoice = Invoice::factory()
            ->forMember($member)
            ->createQuietly([
                'sepa_batch_id' => null,
            ]);

        $result = $this->repository->getInvoiceMailData(InvoiceId::create($invoice->id));

        static::assertNull($result->sepaTransferDate);
    }

    public function test_get_invoice_mail_data_throws_when_invoice_not_found(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->repository->getInvoiceMailData(InvoiceId::create(999_999));
    }

    public function test_get_invoice_mail_data_returns_empty_lines_when_no_lines(): void
    {
        $member = Member::factory()->createQuietly();

        $invoice = Invoice::factory()
            ->forMember($member)
            ->createQuietly();

        $result = $this->repository->getInvoiceMailData(InvoiceId::create($invoice->id));

        static::assertCount(0, $result->lines);
        static::assertSame(0.0, $result->total->price);
        static::assertSame(0.0, $result->total->vat);
    }

    public function test_mark_invoice_as_sent_sets_sent_at(): void
    {
        $invoice = Invoice::factory()->createQuietly(['sent_at' => null]);

        $this->repository->markInvoiceAsSent(InvoiceId::create($invoice->id));

        static::assertNotNull($invoice->fresh()->sent_at);
    }

    public function test_get_invoice_payment_declined_mail_data_returns_the_invoice_and_snapshot_recipient(): void
    {
        $invoice = Invoice::factory()->createQuietly([
            'status' => InvoiceStatus::Declined,
            'date' => '2026-06-01',
            'invoice_number' => 'I-20260001',
            'recipient_name' => 'Test Lid',
            'recipient_email' => 'gezin@example.com',
        ]);

        InvoiceLine::factory()->for($invoice)->createQuietly([
            'price' => 50.00,
            'quantity' => 2,
            'vat' => 10.50,
        ]);

        $result = $this->repository->getInvoicePaymentDeclinedMailData(InvoiceId::create($invoice->id));

        static::assertSame($invoice->id, $result->invoiceId->value);
        static::assertSame('I-20260001', $result->invoiceNumber);
        static::assertSame('Test Lid', $result->recipient->name);
        static::assertSame('gezin@example.com', $result->recipient->email);
        static::assertSame('2026-06-01', $result->invoiceDate->format('Y-m-d'));
        static::assertSame(100.00, $result->total->price);
        static::assertFalse($result->mandateNeedsReauthorisation);
    }

    public function test_get_invoice_payment_declined_mail_data_throws_for_a_non_declined_invoice(): void
    {
        $invoice = Invoice::factory()->createQuietly(['status' => InvoiceStatus::Pending]);

        $this->expectInvoicePaymentDeclinedMailUnavailable(
            InvoiceId::create($invoice->id),
            InvoicePaymentDeclinedMailUnavailableReason::InvoiceNotDeclined,
        );
    }

    public function test_get_invoice_payment_declined_mail_data_throws_for_a_credit_invoice(): void
    {
        $original = Invoice::factory()->createQuietly(['status' => InvoiceStatus::Paid]);
        $credit = Invoice::factory()->createQuietly([
            'status' => InvoiceStatus::Declined,
            'credit_invoice_id' => $original->id,
        ]);

        $this->expectInvoicePaymentDeclinedMailUnavailable(
            InvoiceId::create($credit->id),
            InvoicePaymentDeclinedMailUnavailableReason::InvoiceIsCreditInvoice,
        );
    }

    public function test_get_invoice_payment_declined_mail_data_throws_for_an_invoice_that_has_a_credit_invoice(): void
    {
        $invoice = Invoice::factory()->createQuietly(['status' => InvoiceStatus::Declined]);

        Invoice::factory()->createQuietly([
            'status' => InvoiceStatus::Open,
            'credit_invoice_id' => $invoice->id,
        ]);

        $this->expectInvoicePaymentDeclinedMailUnavailable(
            InvoiceId::create($invoice->id),
            InvoicePaymentDeclinedMailUnavailableReason::InvoiceIsCreditInvoice,
        );
    }

    public function test_get_invoice_payment_declined_mail_data_throws_when_the_recipient_email_is_blank(): void
    {
        $invoice = Invoice::factory()->createQuietly([
            'status' => InvoiceStatus::Declined,
            'recipient_email' => '',
        ]);

        $this->expectInvoicePaymentDeclinedMailUnavailable(
            InvoiceId::create($invoice->id),
            InvoicePaymentDeclinedMailUnavailableReason::RecipientEmailMissing,
        );
    }

    public function test_get_invoice_payment_declined_mail_data_throws_when_the_invoice_does_not_exist(): void
    {
        $this->expectInvoicePaymentDeclinedMailUnavailable(
            InvoiceId::create(999_999),
            InvoicePaymentDeclinedMailUnavailableReason::InvoiceNotFound,
        );
    }

    public function test_get_invoice_payment_declined_mail_data_defaults_the_reauthorisation_flag_to_false(): void
    {
        $member = Member::factory()->createQuietly();
        PaymentInformation::factory()->for($member)->createQuietly([
            'mandate_accepted_date' => '2025-01-15',
        ]);

        $invoice = Invoice::factory()
            ->forMember($member)
            ->createQuietly(['status' => InvoiceStatus::Declined]);

        $result = $this->repository->getInvoicePaymentDeclinedMailData(InvoiceId::create($invoice->id));

        static::assertFalse($result->mandateNeedsReauthorisation);
    }

    public function test_get_invoice_payment_declined_mail_data_uses_the_invoice_recipient_snapshot(): void
    {
        $member = Member::factory()->createQuietly(['email' => 'member@example.com']);

        $invoice = Invoice::factory()
            ->forMember($member)
            ->createQuietly([
                'status' => InvoiceStatus::Declined,
                'recipient_email' => 'gezin@example.com',
            ]);

        $member->update(['email' => 'new-address@example.com']);

        $result = $this->repository->getInvoicePaymentDeclinedMailData(InvoiceId::create($invoice->id));

        static::assertSame('gezin@example.com', $result->recipient->email);
    }

    private function expectInvoicePaymentDeclinedMailUnavailable(
        InvoiceId $invoiceId,
        InvoicePaymentDeclinedMailUnavailableReason $reason,
    ): void {
        try {
            $this->repository->getInvoicePaymentDeclinedMailData($invoiceId);

            static::fail('Expected an InvoicePaymentDeclinedMailUnavailableException.');
        } catch (InvoicePaymentDeclinedMailUnavailableException $exception) {
            static::assertSame($invoiceId, $exception->invoiceId);
            static::assertSame($reason, $exception->reason);
        }
    }
}
