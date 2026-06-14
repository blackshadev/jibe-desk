<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Invoices;

use App\Domain\Invoices\InvoiceBatchId;
use App\Domain\Invoices\InvoiceBatchStatus;
use App\Domain\Invoices\InvoiceStatus;
use App\Infrastructure\Invoices\InvoiceBatchRepositoryDb;
use App\Models\Invoice;
use App\Models\InvoiceBatch;
use App\Models\InvoiceLine;
use App\Models\Member;
use App\Models\PaymentInformation;
use Carbon\CarbonImmutable;
use DomainException;
use Tests\FeatureTestCase;

final class InvoiceBatchRepositoryDbTest extends FeatureTestCase
{
    private InvoiceBatchRepositoryDb $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new InvoiceBatchRepositoryDb();
    }

    public function testCreateBatch(): void
    {
        $date = CarbonImmutable::parse('2026-05-15');

        $batchId = $this->repository->create($date, InvoiceBatchStatus::Open);

        $this->assertDatabaseHas('invoice_batches', [
            'id' => $batchId->value,
            'status' => InvoiceBatchStatus::Open->value,
        ]);
    }

    public function testAddOpenInvoicesFromBatchMonth(): void
    {
        $batch = InvoiceBatch::factory()->create([
            'invoice_date' => '2026-05-13',
        ]);
        $otherBatch = InvoiceBatch::factory()->create();

        $openInvoices = Invoice::factory()
            ->createManyQuietly([
                [
                    'status' => InvoiceStatus::Open,
                    'invoice_batch_id' => null,
                    'date' => '2026-04-10',
                ],
                [
                    'status' => InvoiceStatus::Open,
                    'invoice_batch_id' => null,
                    'date' => '2026-05-10',
                ],
            ]);

        $otherInvoices = Invoice::factory()
            ->createManyQuietly([
                [
                    'status' => InvoiceStatus::Open,
                    'invoice_batch_id' => null,
                    'date' => '2026-06-01', // not in previous month
                ],
                [
                    'status' => InvoiceStatus::Paid,
                    'invoice_batch_id' => null,
                    'date' => '2026-04-13', // Previous month but already paid
                ],
                [
                    'status' => InvoiceStatus::Open,
                    'invoice_batch_id' => $otherBatch->id, // other batch
                    'date' => '2026-04-01',
                ],
            ]);

        $this->repository->addOpenInvoicesFromBatchMonth(InvoiceBatchId::create($batch->id));

        foreach ($openInvoices as $openInvoice) {
            $this->assertDatabaseHas('invoices', [
                'id' => $openInvoice->id,
                'invoice_batch_id' => $batch->id,
            ]);
        }

        foreach ($otherInvoices as $otherInvoice) {
            $this->assertDatabaseMissing('invoices', [
                'id' => $otherInvoice->id,
                'invoice_batch_id' => $batch->id,
            ]);
        }
    }

    public function testCloseBatch(): void
    {
        $batch = InvoiceBatch::factory()->create(['status' => InvoiceBatchStatus::Open]);

        $this->repository->closeBatch(InvoiceBatchId::create($batch->id));

        $this->assertDatabaseHas('invoice_batches', [
            'id' => $batch->id,
            'status' => InvoiceBatchStatus::Pending->value,
        ]);
    }

    public function testCompleteBatch(): void
    {
        $batch = InvoiceBatch::factory()->create(['status' => InvoiceBatchStatus::Pending]);

        Invoice::factory()
            ->forBatch($batch)
            ->createQuietly(['status' => InvoiceStatus::Paid]);

        Invoice::factory()
            ->forBatch($batch)
            ->createQuietly(['status' => InvoiceStatus::Declined]);

        $this->repository->completeBatch(InvoiceBatchId::create($batch->id));

        $this->assertDatabaseHas('invoice_batches', [
            'id' => $batch->id,
            'status' => InvoiceBatchStatus::Completed->value,
        ]);
    }

    public function testCompleteBatchFailsWithOpenInvoices(): void
    {
        $batch = InvoiceBatch::factory()->create(['status' => InvoiceBatchStatus::Pending]);

        Invoice::factory()
            ->forBatch($batch)
            ->createQuietly(['status' => InvoiceStatus::Open]);

        $this->expectException(DomainException::class);

        $this->repository->completeBatch(InvoiceBatchId::create($batch->id));
    }

    public function testCompleteBatchFailsWithPendingInvoices(): void
    {
        $batch = InvoiceBatch::factory()->create(['status' => InvoiceBatchStatus::Pending]);

        Invoice::factory()
            ->forBatch($batch)
            ->createQuietly(['status' => InvoiceStatus::Pending]);

        $this->expectException(DomainException::class);

        $this->repository->completeBatch(InvoiceBatchId::create($batch->id));
    }

    public function testGetInvoicesForExport(): void
    {
        $batch = InvoiceBatch::factory()->create();
        $member = Member::factory()->createQuietly();

        PaymentInformation::factory()
            ->createQuietly([
                'member_id' => $member->id,
                'banking_account_number' => 'NL91ABNA0417164300',
                'banking_bic' => 'ABNANL2A',
                'uuid' => 'test-mandate-uuid',
                'mandate_accepted_date' => '2025-01-15',
            ]);

        $invoice = Invoice::factory()
            ->forMember($member)
            ->forBatch($batch)
            ->createQuietly();

        InvoiceLine::factory()
            ->state(['invoice_id' => $invoice->id])
            ->createQuietly(['price' => 10.00, 'quantity' => 2, 'vat' => 4.20]);

        $result = $this->repository->getInvoicesForExport(InvoiceBatchId::create($batch->id));

        static::assertCount(1, $result);
        static::assertSame($invoice->invoice_number, $result[0]->invoiceNumber);
        static::assertSame($member->name, $result[0]->recipientName);
        static::assertSame('NL91ABNA0417164300', $result[0]->iban);
        static::assertSame('ABNANL2A', $result[0]->bic);
        static::assertSame('test-mandate-uuid', $result[0]->mandateId);
        static::assertGreaterThan(0.0, $result[0]->total->price);
        static::assertGreaterThan(0.0, $result[0]->total->vat);
        static::assertSame(
            (int) round($result[0]->total->price * 100),
            $result[0]->amountInCents(),
        );
    }
}
