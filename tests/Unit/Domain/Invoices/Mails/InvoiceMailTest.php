<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices\Mails;

use App\Domain\Invoices\CompoundPrice;
use App\Domain\Invoices\InvoiceMailData;
use App\Domain\Invoices\InvoiceMailLine;
use App\Domain\Invoices\Mails\InvoiceMail;
use App\Domain\Invoices\SepaConfiguration;
use App\Domain\Mail\Recipient;
use App\Domain\Mail\Related;
use App\Models\Invoice;
use Carbon\CarbonImmutable;
use Illuminate\Mail\Mailables\Content;
use Tests\UnitTestCase;

final class InvoiceMailTest extends UnitTestCase
{
    public function test_it_exposes_the_recipient(): void
    {
        $recipient = new Recipient('Vries, Jan de', 'jan@example.com');
        $mail = new InvoiceMail(
            $this->createInvoiceData(recipient: $recipient),
            $this->createSepaConfiguration(),
        );

        static::assertSame($recipient, $mail->to());
    }

    public function test_subject_is_the_invoice_number(): void
    {
        $mail = new InvoiceMail(
            $this->createInvoiceData(invoiceNumber: 'INV-2026-001'),
            $this->createSepaConfiguration(),
        );

        static::assertSame('Factuur INV-2026-001', $mail->subject());
    }

    public function test_content_uses_the_invoice_template_and_passes_the_data(): void
    {
        $recipient = new Recipient('Vries, Jan de', 'jan@example.com');
        $invoiceDate = CarbonImmutable::parse('2026-05-25');
        $sepaTransferDate = CarbonImmutable::parse('2026-06-01');
        $total = new CompoundPrice(100.0, 21.0);
        $lines = [
            new InvoiceMailLine('Membership fee', 1.0, new CompoundPrice(50.0, 10.5), new CompoundPrice(50.0, 10.5)),
        ];
        $sepa = $this->createSepaConfiguration();

        $mail = new InvoiceMail(
            new InvoiceMailData(
                invoiceId: 42,
                invoiceNumber: 'INV-2026-001',
                recipient: $recipient,
                recipientIban: 'NL91ABNA0417164300',
                recipientAddress: 'Surfstrand 2, 1324CT Almere',
                invoiceDate: $invoiceDate,
                total: $total,
                lines: $lines,
                sepaTransferDate: $sepaTransferDate,
            ),
            $sepa,
        );

        $content = $mail->content();

        static::assertInstanceOf(Content::class, $content);
        static::assertSame('mail.invoice', $content->markdown);
        static::assertSame('Vries, Jan de', $content->with['memberName']);
        static::assertSame('jan@example.com', $content->with['email']);
        static::assertSame('Surfstrand 2, 1324CT Almere', $content->with['address']);
        static::assertSame('NL91ABNA0417164300', $content->with['recipientIban']);
        static::assertSame('INV-2026-001', $content->with['invoiceNumber']);
        static::assertSame($invoiceDate, $content->with['invoiceDate']);
        static::assertSame($total, $content->with['total']);
        static::assertSame($lines, $content->with['lines']);
        static::assertSame($sepaTransferDate, $content->with['sepaTransferDate']);
        static::assertSame($sepa->creditorIban, $content->with['creditorIban']);
        static::assertSame($sepa->creditorName, $content->with['creditorAccountName']);
    }

    public function test_related_points_to_the_invoice(): void
    {
        $mail = new InvoiceMail(
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
    ): InvoiceMailData {
        return new InvoiceMailData(
            invoiceId: 42,
            invoiceNumber: $invoiceNumber,
            recipient: $recipient ?? new Recipient('Vries, Jan de', 'jan@example.com'),
            recipientIban: 'NL91ABNA0417164300',
            recipientAddress: 'Surfstrand 2, 1324CT Almere',
            invoiceDate: CarbonImmutable::parse('2026-05-25'),
            total: new CompoundPrice(100.0, 21.0),
            lines: [],
            sepaTransferDate: CarbonImmutable::parse('2026-06-01'),
        );
    }

    private function createSepaConfiguration(): SepaConfiguration
    {
        return new SepaConfiguration(
            creditorId: 'NL12ZZZ123456780000',
            creditorName: 'Watersportvereniging Almere Centraal',
            creditorIban: 'NL91ABNA0417164300',
            creditorBic: 'ABNANL2A',
        );
    }
}
