<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Billing\Invoices\Mails;

use App\Domain\Billing\Invoices\InvoiceId;
use App\Domain\Billing\Invoices\InvoicePaymentDeclinedMailData;
use App\Domain\Billing\Invoices\Mails\InvoicePaymentDeclinedMail;
use App\Domain\Billing\Payment\SepaConfiguration;
use App\Domain\Mail\Recipient;
use App\Domain\Mail\Related;
use App\Domain\SharedKernel\CompoundPrice;
use App\Models\Invoice;
use Carbon\CarbonImmutable;
use Illuminate\Mail\Mailables\Content;
use Tests\UnitTestCase;

final class InvoicePaymentDeclinedMailTest extends UnitTestCase
{
    public function test_it_exposes_the_recipient(): void
    {
        $recipient = new Recipient('Vries, Jan de', 'jan@example.com');
        $mail = new InvoicePaymentDeclinedMail(
            $this->createInvoiceData(recipient: $recipient),
            $this->createSepaConfiguration(),
        );

        static::assertSame($recipient, $mail->to());
    }

    public function test_subject_states_the_payment_failed(): void
    {
        $mail = new InvoicePaymentDeclinedMail(
            $this->createInvoiceData(invoiceNumber: 'INV-2026-001'),
            $this->createSepaConfiguration(),
        );

        static::assertSame('Betaling van factuur INV-2026-001 is niet gelukt', $mail->subject());
    }

    public function test_content_uses_the_declined_notice_template_and_passes_the_data(): void
    {
        $recipient = new Recipient('Vries, Jan de', 'jan@example.com');
        $invoiceDate = CarbonImmutable::parse('2026-05-25');
        $total = new CompoundPrice(100.0, 21.0);
        $sepa = $this->createSepaConfiguration();

        $mail = new InvoicePaymentDeclinedMail(
            new InvoicePaymentDeclinedMailData(
                invoiceId: InvoiceId::create(42),
                invoiceNumber: 'INV-2026-001',
                recipient: $recipient,
                invoiceDate: $invoiceDate,
                total: $total,
                mandateNeedsReauthorisation: false,
            ),
            $sepa,
        );

        $content = $mail->content();

        static::assertInstanceOf(Content::class, $content);
        static::assertSame('mail.invoice-payment-declined', $content->markdown);
        static::assertSame('Vries, Jan de', $content->with['memberName']);
        static::assertSame('INV-2026-001', $content->with['invoiceNumber']);
        static::assertSame($invoiceDate, $content->with['invoiceDate']);
        static::assertSame($total, $content->with['total']);
        static::assertSame($sepa->creditorIban, $content->with['creditorIban']);
        static::assertSame($sepa->creditorName, $content->with['creditorAccountName']);
        static::assertFalse($content->with['mandateNeedsReauthorisation']);
    }

    public function test_content_passes_the_reauthorisation_flag(): void
    {
        $mail = new InvoicePaymentDeclinedMail(
            $this->createInvoiceData(mandateNeedsReauthorisation: true),
            $this->createSepaConfiguration(),
        );

        $content = $mail->content();

        static::assertTrue($content->with['mandateNeedsReauthorisation']);
    }

    public function test_related_points_to_the_invoice(): void
    {
        $mail = new InvoicePaymentDeclinedMail(
            $this->createInvoiceData(),
            $this->createSepaConfiguration(),
        );

        $related = $mail->related();

        static::assertInstanceOf(Related::class, $related);
        static::assertSame(Invoice::class, $related->class);
        static::assertSame(42, $related->id);
    }

    private function createInvoiceData(
        string $invoiceNumber = 'INV-2026-001',
        ?Recipient $recipient = null,
        bool $mandateNeedsReauthorisation = false,
    ): InvoicePaymentDeclinedMailData {
        return new InvoicePaymentDeclinedMailData(
            invoiceId: InvoiceId::create(42),
            invoiceNumber: $invoiceNumber,
            recipient: $recipient ?? new Recipient('Vries, Jan de', 'jan@example.com'),
            invoiceDate: CarbonImmutable::parse('2026-05-25'),
            total: new CompoundPrice(100.0, 21.0),
            mandateNeedsReauthorisation: $mandateNeedsReauthorisation,
        );
    }

    private function createSepaConfiguration(): SepaConfiguration
    {
        return new SepaConfiguration(
            creditorId: 'NL12ZZZ123456780000',
            creditorName: 'Watersportvereniging Almere Centraal',
            creditorIban: 'NL91ABNA0417164300',
            creditorBic: 'ABNANL2A',
            noticePeriodDays: 14,
        );
    }
}
