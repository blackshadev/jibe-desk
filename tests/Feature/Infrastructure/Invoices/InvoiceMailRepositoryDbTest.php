<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Invoices;

use App\Domain\Invoices\InvoiceId;
use App\Infrastructure\Invoices\InvoiceMailRepositoryDb;
use App\Models\Invoice;
use App\Models\InvoiceBatch;
use App\Models\InvoiceLine;
use App\Models\Member;
use App\Models\PaymentInformation;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Tests\FeatureTestCase;

final class InvoiceMailRepositoryDbTest extends FeatureTestCase
{
    private InvoiceMailRepositoryDb $repository;

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

        $batch = InvoiceBatch::factory()->createQuietly([
            'invoice_date' => '2026-06-30',
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
    }

    public function test_get_invoice_mail_data_sets_sepa_transfer_date_when_mandate_and_batch_present(): void
    {
        $member = Member::factory()->createQuietly();
        PaymentInformation::factory()->for($member)->createQuietly([
            'mandate_accepted_date' => '2025-01-15',
        ]);

        $batch = InvoiceBatch::factory()->createQuietly([
            'invoice_date' => '2026-07-01',
        ]);

        $invoice = Invoice::factory()
            ->forMember($member)
            ->forBatch($batch)
            ->createQuietly();

        $result = $this->repository->getInvoiceMailData(InvoiceId::create($invoice->id));

        static::assertNotNull($result->sepaTransferDate);
        static::assertSame('2026-07-01', $result->sepaTransferDate->format('Y-m-d'));
    }

    public function test_get_invoice_mail_data_sepa_transfer_date_null_when_no_payment_information(): void
    {
        $member = Member::factory()->createQuietly();

        $batch = InvoiceBatch::factory()->createQuietly([
            'invoice_date' => '2026-07-01',
        ]);

        $invoice = Invoice::factory()
            ->forMember($member)
            ->forBatch($batch)
            ->createQuietly();

        $result = $this->repository->getInvoiceMailData(InvoiceId::create($invoice->id));

        static::assertNull($result->sepaTransferDate);
    }

    public function test_get_invoice_mail_data_sepa_transfer_date_null_when_no_invoice_batch(): void
    {
        $member = Member::factory()->createQuietly();
        PaymentInformation::factory()->for($member)->createQuietly([
            'mandate_accepted_date' => '2025-01-15',
        ]);

        $invoice = Invoice::factory()
            ->forMember($member)
            ->createQuietly([
                'invoice_batch_id' => null,
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
}
