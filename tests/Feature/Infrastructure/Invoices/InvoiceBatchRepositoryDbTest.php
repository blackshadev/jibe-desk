<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Invoices;

use App\Domain\Invoices\InvoiceBatchId;
use App\Domain\Invoices\InvoiceBatchStatus;
use App\Domain\Invoices\InvoiceId;
use App\Domain\Invoices\InvoiceStatus;
use App\Infrastructure\Invoices\InvoiceBatchRepositoryDb;
use App\Models\Invoice;
use App\Models\InvoiceBatch;
use App\Models\InvoiceLine;
use App\Models\Member;
use App\Models\PaymentInformation;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Tests\FeatureTestCase;

final class InvoiceBatchRepositoryDbTest extends FeatureTestCase
{
    private InvoiceBatchRepositoryDb $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new InvoiceBatchRepositoryDb();
    }

    public function test_create_batch(): void
    {
        $date = CarbonImmutable::parse('2026-05-15');

        $batchId = $this->repository->create($date, InvoiceBatchStatus::Open);

        $this->assertDatabaseHas('invoice_batches', [
            'id' => $batchId->value,
            'status' => InvoiceBatchStatus::Open->value,
        ]);
    }

    public function test_add_open_invoices_from_batch_month(): void
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

    public function test_close_batch(): void
    {
        $batch = InvoiceBatch::factory()->create(['status' => InvoiceBatchStatus::Open]);

        $this->repository->closeBatch(InvoiceBatchId::create($batch->id));

        $this->assertDatabaseHas('invoice_batches', [
            'id' => $batch->id,
            'status' => InvoiceBatchStatus::Pending->value,
        ]);
    }

    public function test_complete_batch(): void
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

    public function test_complete_batch_fails_with_open_invoices(): void
    {
        $batch = InvoiceBatch::factory()->create(['status' => InvoiceBatchStatus::Pending]);

        Invoice::factory()
            ->forBatch($batch)
            ->createQuietly(['status' => InvoiceStatus::Open]);

        $this->expectException(DomainException::class);

        $this->repository->completeBatch(InvoiceBatchId::create($batch->id));
    }

    public function test_complete_batch_fails_with_pending_invoices(): void
    {
        $batch = InvoiceBatch::factory()->create(['status' => InvoiceBatchStatus::Pending]);

        Invoice::factory()
            ->forBatch($batch)
            ->createQuietly(['status' => InvoiceStatus::Pending]);

        $this->expectException(DomainException::class);

        $this->repository->completeBatch(InvoiceBatchId::create($batch->id));
    }

    public function test_get_invoices_for_export(): void
    {
        $batch = InvoiceBatch::factory()->create();
        $member = Member::factory()->createQuietly();

        PaymentInformation::factory()
            ->createQuietly([
                'member_id' => $member->id,
                'banking_account_number' => 'NL91ABNA0417164300',
                'banking_bic' => 'ABNANL2A',
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
        static::assertSame('C000001-000001', $result[0]->mandateId->value);
        static::assertGreaterThan(0.0, $result[0]->total->price);
        static::assertGreaterThan(0.0, $result[0]->total->vat);
        static::assertSame(
            (int) round($result[0]->total->price * 100),
            $result[0]->amountInCents(),
        );
    }

    public function test_get_pending_invoices_for_batch_returns_pending_invoices(): void
    {
        $batch = InvoiceBatch::factory()->create();

        [$invoice1, $invoice2] = Invoice::factory()
            ->forBatch($batch)
            ->state(['status' => InvoiceStatus::Pending])
            ->count(2)
            ->createManyQuietly()
            ->all();

        $result = $this->repository->getPendingInvoicesForBatch(InvoiceBatchId::create($batch->id));

        static::assertCount(2, $result);
        static::assertEquals(
            [InvoiceId::create($invoice1->id), InvoiceId::create($invoice2->id)],
            $result,
        );
    }

    public function test_get_pending_invoices_for_batch_excludes_non_pending_invoices(): void
    {
        $batch = InvoiceBatch::factory()->create();

        $pending = Invoice::factory()
            ->forBatch($batch)
            ->createManyQuietly([
                ['status' => InvoiceStatus::Pending],
                ['status' => InvoiceStatus::Open],
                ['status' => InvoiceStatus::Paid],
                ['status' => InvoiceStatus::Declined],
            ])
            ->first();

        $result = $this->repository->getPendingInvoicesForBatch(InvoiceBatchId::create($batch->id));

        static::assertCount(1, $result);
        static::assertEquals(InvoiceId::create($pending->id), $result[0]);
    }

    public function test_get_pending_invoices_for_batch_excludes_invoices_from_other_batches(): void
    {
        $batch = InvoiceBatch::factory()->create();
        $otherBatch = InvoiceBatch::factory()->create();

        $invoice = Invoice::factory()
            ->forBatch($batch)
            ->createQuietly(['status' => InvoiceStatus::Pending]);

        Invoice::factory()
            ->forBatch($otherBatch)
            ->createQuietly(['status' => InvoiceStatus::Pending]);

        $result = $this->repository->getPendingInvoicesForBatch(InvoiceBatchId::create($batch->id));

        static::assertCount(1, $result);
        static::assertEquals(InvoiceId::create($invoice->id), $result[0]);
    }

    public function test_get_pending_invoices_for_batch_returns_empty_array_when_no_pending_invoices(): void
    {
        $batch = InvoiceBatch::factory()->create();

        Invoice::factory()
            ->forBatch($batch)
            ->createQuietly(['status' => InvoiceStatus::Open]);

        $result = $this->repository->getPendingInvoicesForBatch(InvoiceBatchId::create($batch->id));

        static::assertEmpty($result);
    }

    public function test_get_pending_invoices_for_batch_returns_empty_array_for_nonexistent_batch(): void
    {
        $result = $this->repository->getPendingInvoicesForBatch(InvoiceBatchId::create(999_999));

        static::assertEmpty($result);
    }

    public function test_get_batch_date_returns_invoice_date(): void
    {
        $date = CarbonImmutable::parse('2026-06-15');
        $batch = InvoiceBatch::factory()->create(['invoice_date' => $date]);

        $result = $this->repository->getBatchDate(InvoiceBatchId::create($batch->id));

        static::assertEquals($date->toDateString(), $result->format('Y-m-d'));
    }

    public function test_get_batch_date_throws_for_nonexistent_batch(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->repository->getBatchDate(InvoiceBatchId::create(999_999));
    }

    public function test_get_batch_email_data_returns_aggregated_data(): void
    {
        $date = CarbonImmutable::parse('2026-06-15');
        $batch = InvoiceBatch::factory()->create(['invoice_date' => $date]);
        $otherBatch = InvoiceBatch::factory()->create();

        [$invoice1, $invoice2] = Invoice::factory()
            ->forBatch($batch)
            ->count(2)
            ->createManyQuietly();

        InvoiceLine::factory()
            ->state(['invoice_id' => $invoice1->id])
            ->createQuietly(['price' => 10.00, 'quantity' => 2, 'vat' => 4.20]);
        InvoiceLine::factory()
            ->state(['invoice_id' => $invoice1->id])
            ->createQuietly(['price' => 5.00, 'quantity' => 1, 'vat' => 1.05]);
        InvoiceLine::factory()
            ->state(['invoice_id' => $invoice2->id])
            ->createQuietly(['price' => 3.00, 'quantity' => 4, 'vat' => 0.63]);

        Invoice::factory()
            ->forBatch($otherBatch)
            ->createQuietly();
        InvoiceLine::factory()
            ->state(['invoice_id' => Invoice::query()->where('invoice_batch_id', $otherBatch->id)->value('id')])
            ->createQuietly(['price' => 99.00, 'quantity' => 1, 'vat' => 20.79]);

        $result = $this->repository->getBatchEmailData(InvoiceBatchId::create($batch->id));

        static::assertSame($batch->id, $result->id->value);
        static::assertSame($date->toDateString(), $result->invoiceDate->format('Y-m-d'));
        static::assertSame(2, $result->invoiceCount);
        static::assertEqualsWithDelta((10.00 * 2) + 5.00 + (3.00 * 4), $result->total->price, 0.001);
        static::assertEqualsWithDelta((4.20 * 2) + 1.05 + (0.63 * 4), $result->total->vat, 0.001);
    }

    public function test_get_batch_email_data_returns_empty_values_for_batch_without_invoices(): void
    {
        $date = CarbonImmutable::parse('2026-06-15');
        $batch = InvoiceBatch::factory()->create(['invoice_date' => $date]);

        $result = $this->repository->getBatchEmailData(InvoiceBatchId::create($batch->id));

        static::assertSame($date->toDateString(), $result->invoiceDate->format('Y-m-d'));
        static::assertSame(0, $result->invoiceCount);
        static::assertSame(0.0, $result->total->price);
        static::assertSame(0.0, $result->total->vat);
    }

    public function test_get_batch_email_data_throws_for_nonexistent_batch(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->repository->getBatchEmailData(InvoiceBatchId::create(999_999));
    }

    public function test_attach_invoice(): void
    {
        $batch = InvoiceBatch::factory()->createQuietly();
        $invoice = Invoice::factory()->createQuietly([
            'invoice_batch_id' => null,
        ]);

        $this->repository->attachInvoice(new InvoiceBatchId($batch->id), new InvoiceId($invoice->id));

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'invoice_batch_id' => $batch->id,
        ]);
    }
}
