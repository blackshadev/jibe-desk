<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Billing\Payment;

use App\Domain\Billing\Invoices\InvoiceId;
use App\Domain\Billing\Invoices\InvoiceStatus;
use App\Domain\Billing\Payment\SepaBatchId;
use App\Domain\Billing\Payment\SepaBatchStatus;
use App\Domain\Billing\PurchaseOrders\PurchaseOrderStatus;
use App\Infrastructure\Billing\Payment\SepaBatchRepositoryDb;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Member;
use App\Models\PaymentInformation;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\SepaBatch;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Override;
use Tests\FeatureTestCase;

final class SepaBatchRepositoryDbTest extends FeatureTestCase
{
    private SepaBatchRepositoryDb $repository;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new SepaBatchRepositoryDb();
    }

    public function test_create_batch(): void
    {
        $date = CarbonImmutable::parse('2026-05-15');
        $sepaDate = CarbonImmutable::parse('2026-05-29');

        $batchId = $this->repository->create($date, $sepaDate, SepaBatchStatus::Open);

        $this->assertDatabaseHas('sepa_batches', [
            'id' => $batchId->value,
            'status' => SepaBatchStatus::Open->value,
            'sepa_transfer_date' => '2026-05-29 00:00:00',
        ]);
    }

    public function test_add_open_invoices_from_batch_month(): void
    {
        $batch = SepaBatch::factory()->create([
            'invoice_date' => '2026-05-13',
        ]);
        $otherBatch = SepaBatch::factory()->create();

        $openInvoices = Invoice::factory()
            ->createManyQuietly([
                [
                    'status' => InvoiceStatus::Open,
                    'sepa_batch_id' => null,
                    'date' => '2026-04-10',
                ],
                [
                    'status' => InvoiceStatus::Open,
                    'sepa_batch_id' => null,
                    'date' => '2026-05-10',
                ],
            ]);

        $otherInvoices = Invoice::factory()
            ->createManyQuietly([
                [
                    'status' => InvoiceStatus::Open,
                    'sepa_batch_id' => null,
                    'date' => '2026-06-01', // not in previous month
                ],
                [
                    'status' => InvoiceStatus::Paid,
                    'sepa_batch_id' => null,
                    'date' => '2026-04-13', // Previous month but already paid
                ],
                [
                    'status' => InvoiceStatus::Open,
                    'sepa_batch_id' => $otherBatch->id, // other batch
                    'date' => '2026-04-01',
                ],
            ]);

        $attachedCount = $this->repository->addOpenInvoicesFromBatchMonth(SepaBatchId::create($batch->id));

        static::assertSame(2, $attachedCount);

        foreach ($openInvoices as $openInvoice) {
            $this->assertDatabaseHas('invoices', [
                'id' => $openInvoice->id,
                'sepa_batch_id' => $batch->id,
            ]);
        }

        foreach ($otherInvoices as $otherInvoice) {
            $this->assertDatabaseMissing('invoices', [
                'id' => $otherInvoice->id,
                'sepa_batch_id' => $batch->id,
            ]);
        }
    }

    public function test_mark_generation_started(): void
    {
        $batch = SepaBatch::factory()->create();

        $this->repository->markGenerationStarted(SepaBatchId::create($batch->id), 25);

        $this->assertDatabaseHas('sepa_batches', [
            'id' => $batch->id,
            'generation_expected_invoices' => 25,
        ]);

        static::assertNotNull($batch->fresh()->generation_started_at);
    }

    public function test_mark_generation_finished(): void
    {
        $batch = SepaBatch::factory()->generating()->create();

        $this->repository->markGenerationFinished(SepaBatchId::create($batch->id));

        static::assertNotNull($batch->fresh()->generation_finished_at);
    }

    public function test_close_batch(): void
    {
        $batch = SepaBatch::factory()->create(['status' => SepaBatchStatus::Open]);

        $this->repository->closeBatch(SepaBatchId::create($batch->id));

        $this->assertDatabaseHas('sepa_batches', [
            'id' => $batch->id,
            'status' => SepaBatchStatus::Pending->value,
        ]);
    }

    public function test_complete_batch(): void
    {
        $batch = SepaBatch::factory()->create(['status' => SepaBatchStatus::Pending]);

        Invoice::factory()
            ->forBatch($batch)
            ->createQuietly(['status' => InvoiceStatus::Paid]);

        Invoice::factory()
            ->forBatch($batch)
            ->createQuietly(['status' => InvoiceStatus::Declined]);

        $this->repository->completeBatch(SepaBatchId::create($batch->id));

        $this->assertDatabaseHas('sepa_batches', [
            'id' => $batch->id,
            'status' => SepaBatchStatus::Completed->value,
        ]);
    }

    public function test_complete_batch_fails_with_open_invoices(): void
    {
        $batch = SepaBatch::factory()->create(['status' => SepaBatchStatus::Pending]);

        Invoice::factory()
            ->forBatch($batch)
            ->createQuietly(['status' => InvoiceStatus::Open]);

        $this->expectException(DomainException::class);

        $this->repository->completeBatch(SepaBatchId::create($batch->id));
    }

    public function test_complete_batch_fails_with_pending_invoices(): void
    {
        $batch = SepaBatch::factory()->create(['status' => SepaBatchStatus::Pending]);

        Invoice::factory()
            ->forBatch($batch)
            ->createQuietly(['status' => InvoiceStatus::Pending]);

        $this->expectException(DomainException::class);

        $this->repository->completeBatch(SepaBatchId::create($batch->id));
    }

    public function test_get_invoices_for_export(): void
    {
        $batch = SepaBatch::factory()->create();
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

        $result = $this->repository->getInvoicesForExport(SepaBatchId::create($batch->id));

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

    public function test_get_purchase_orders_for_export(): void
    {
        $batch = SepaBatch::factory()->create();
        $otherBatch = SepaBatch::factory()->create();

        $purchaseOrder = PurchaseOrder::factory()->create([
            'creditor_name' => 'Supplier Ltd',
            'creditor_iban' => 'NL20RABO0123456789',
            'status' => PurchaseOrderStatus::Pending,
            'sepa_batch_id' => $batch->id,
        ]);
        PurchaseOrderLine::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'price' => 100.00,
            'price_vat' => 21.00,
        ]);

        PurchaseOrder::factory()->create([
            'status' => PurchaseOrderStatus::Pending,
            'sepa_batch_id' => $otherBatch->id,
        ]);

        PurchaseOrder::factory()->create([
            'status' => PurchaseOrderStatus::Pending,
            'sepa_batch_id' => null,
        ]);

        $result = $this->repository->getPurchaseOrdersForExport(SepaBatchId::create($batch->id));

        static::assertCount(1, $result);
        static::assertSame($purchaseOrder->id, $result[0]->purchaseOrderId->value);
        static::assertSame('Supplier Ltd', $result[0]->creditorName);
        static::assertSame('NL20RABO0123456789', $result[0]->creditorIban);
        static::assertSame('PO-' . $purchaseOrder->id, $result[0]->reference());
        static::assertSame(10_000, $result[0]->amountInCents());
    }

    public function test_get_purchase_orders_for_export_excludes_non_pending_purchase_orders(): void
    {
        $batch = SepaBatch::factory()->create();

        foreach ([PurchaseOrderStatus::Declined, PurchaseOrderStatus::Paid, PurchaseOrderStatus::Open] as $status) {
            $purchaseOrder = PurchaseOrder::factory()->create([
                'status' => $status,
                'sepa_batch_id' => $batch->id,
            ]);
            PurchaseOrderLine::factory()->create([
                'purchase_order_id' => $purchaseOrder->id,
                'price' => 50.00,
            ]);
        }

        $result = $this->repository->getPurchaseOrdersForExport(SepaBatchId::create($batch->id));

        static::assertSame([], $result);
    }

    public function test_get_purchase_orders_for_export_uses_net_total(): void
    {
        $batch = SepaBatch::factory()->create();

        $purchaseOrder = PurchaseOrder::factory()->create([
            'creditor_name' => 'Supplier Ltd',
            'creditor_iban' => 'NL20RABO0123456789',
            'status' => PurchaseOrderStatus::Pending,
            'sepa_batch_id' => $batch->id,
        ]);
        PurchaseOrderLine::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'price' => 60.00,
            'price_vat' => 12.60,
        ]);
        PurchaseOrderLine::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'price' => 30.00,
            'price_vat' => 6.30,
        ]);

        $result = $this->repository->getPurchaseOrdersForExport(SepaBatchId::create($batch->id));

        static::assertCount(1, $result);
        static::assertSame(9000, $result[0]->amountInCents());
    }

    public function test_get_pending_invoices_for_batch_returns_pending_invoices(): void
    {
        $batch = SepaBatch::factory()->create();

        [$invoice1, $invoice2] = Invoice::factory()
            ->forBatch($batch)
            ->state(['status' => InvoiceStatus::Pending])
            ->count(2)
            ->createManyQuietly()
            ->all();

        $result = $this->repository->getPendingInvoicesForBatch(SepaBatchId::create($batch->id));

        static::assertCount(2, $result);
        static::assertEquals(
            [InvoiceId::create($invoice1->id), InvoiceId::create($invoice2->id)],
            $result,
        );
    }

    public function test_get_pending_invoices_for_batch_excludes_non_pending_invoices(): void
    {
        $batch = SepaBatch::factory()->create();

        $pending = Invoice::factory()
            ->forBatch($batch)
            ->createManyQuietly([
                ['status' => InvoiceStatus::Pending],
                ['status' => InvoiceStatus::Open],
                ['status' => InvoiceStatus::Paid],
                ['status' => InvoiceStatus::Declined],
            ])
            ->first();

        $result = $this->repository->getPendingInvoicesForBatch(SepaBatchId::create($batch->id));

        static::assertCount(1, $result);
        static::assertEquals(InvoiceId::create($pending->id), $result[0]);
    }

    public function test_get_pending_invoices_for_batch_excludes_invoices_from_other_batches(): void
    {
        $batch = SepaBatch::factory()->create();
        $otherBatch = SepaBatch::factory()->create();

        $invoice = Invoice::factory()
            ->forBatch($batch)
            ->createQuietly(['status' => InvoiceStatus::Pending]);

        Invoice::factory()
            ->forBatch($otherBatch)
            ->createQuietly(['status' => InvoiceStatus::Pending]);

        $result = $this->repository->getPendingInvoicesForBatch(SepaBatchId::create($batch->id));

        static::assertCount(1, $result);
        static::assertEquals(InvoiceId::create($invoice->id), $result[0]);
    }

    public function test_get_pending_invoices_for_batch_returns_empty_array_when_no_pending_invoices(): void
    {
        $batch = SepaBatch::factory()->create();

        Invoice::factory()
            ->forBatch($batch)
            ->createQuietly(['status' => InvoiceStatus::Open]);

        $result = $this->repository->getPendingInvoicesForBatch(SepaBatchId::create($batch->id));

        static::assertEmpty($result);
    }

    public function test_get_pending_invoices_for_batch_returns_empty_array_for_nonexistent_batch(): void
    {
        $result = $this->repository->getPendingInvoicesForBatch(SepaBatchId::create(999_999));

        static::assertEmpty($result);
    }

    public function test_get_sepa_transfer_date_returns_sepa_transfer_date(): void
    {
        $date = CarbonImmutable::parse('2026-06-15');
        $sepaDate = CarbonImmutable::parse('2026-06-29');
        $batch = SepaBatch::factory()->create([
            'invoice_date' => $date,
            'sepa_transfer_date' => $sepaDate,
        ]);

        $result = $this->repository->getSepaTransferDate(SepaBatchId::create($batch->id));

        static::assertEquals($sepaDate->toDateString(), $result->format('Y-m-d'));
    }

    public function test_get_sepa_transfer_date_throws_for_nonexistent_batch(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->repository->getSepaTransferDate(SepaBatchId::create(999_999));
    }

    public function test_mark_invoices_as_pending_overrides_invoice_date(): void
    {
        $batch = SepaBatch::factory()->create(['invoice_date' => '2026-06-15']);

        $invoice = Invoice::factory()
            ->forBatch($batch)
            ->createQuietly([
                'status' => InvoiceStatus::Open,
                'date' => '2026-05-01',
            ]);

        $this->repository->markInvoicesAsPending(SepaBatchId::create($batch->id));

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => InvoiceStatus::Pending->value,
            'date' => '2026-06-15 00:00:00',
        ]);
    }

    public function test_get_batch_email_data_returns_aggregated_data(): void
    {
        $date = CarbonImmutable::parse('2026-06-15');
        $batch = SepaBatch::factory()->create(['invoice_date' => $date]);
        $otherBatch = SepaBatch::factory()->create();

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
            ->state(['invoice_id' => Invoice::query()->where('sepa_batch_id', $otherBatch->id)->value('id')])
            ->createQuietly(['price' => 99.00, 'quantity' => 1, 'vat' => 20.79]);

        $result = $this->repository->getBatchEmailData(SepaBatchId::create($batch->id));

        static::assertSame($batch->id, $result->id->value);
        static::assertSame($date->toDateString(), $result->invoiceDate->format('Y-m-d'));
        static::assertSame(2, $result->invoiceCount);
        static::assertEqualsWithDelta((10.00 * 2) + 5.00 + (3.00 * 4), $result->total->price, 0.001);
        static::assertEqualsWithDelta((4.20 * 2) + 1.05 + (0.63 * 4), $result->total->vat, 0.001);
    }

    public function test_get_batch_email_data_returns_empty_values_for_batch_without_invoices(): void
    {
        $date = CarbonImmutable::parse('2026-06-15');
        $batch = SepaBatch::factory()->create(['invoice_date' => $date]);

        $result = $this->repository->getBatchEmailData(SepaBatchId::create($batch->id));

        static::assertSame($date->toDateString(), $result->invoiceDate->format('Y-m-d'));
        static::assertSame(0, $result->invoiceCount);
        static::assertSame(0.0, $result->total->price);
        static::assertSame(0.0, $result->total->vat);
    }

    public function test_get_batch_email_data_throws_for_nonexistent_batch(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->repository->getBatchEmailData(SepaBatchId::create(999_999));
    }

    public function test_attach_invoice(): void
    {
        $batch = SepaBatch::factory()->createQuietly();
        $invoice = Invoice::factory()->createQuietly([
            'sepa_batch_id' => null,
        ]);

        $this->repository->attachInvoice(new SepaBatchId($batch->id), new InvoiceId($invoice->id));

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'sepa_batch_id' => $batch->id,
        ]);
    }
}
