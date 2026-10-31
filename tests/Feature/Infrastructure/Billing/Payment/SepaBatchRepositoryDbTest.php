<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Billing\Payment;

use App\Domain\Billing\Invoices\InvoiceId;
use App\Domain\Billing\Invoices\InvoiceIdList;
use App\Domain\Billing\Invoices\InvoiceStatus;
use App\Domain\Billing\Payment\MandateSequenceResolver;
use App\Domain\Billing\Payment\Readiness\ExportConfirmation;
use App\Domain\Billing\Payment\Readiness\ExportOverride;
use App\Domain\Billing\Payment\Readiness\ExportReadinessReason;
use App\Domain\Billing\Payment\SepaBatchId;
use App\Domain\Billing\Payment\SepaBatchStatus;
use App\Domain\Billing\Payment\SequenceType;
use App\Domain\Billing\PurchaseOrders\PurchaseOrderApprovalStatus;
use App\Domain\Billing\PurchaseOrders\PurchaseOrderStatus;
use App\Infrastructure\Billing\Invoices\CreditInvoiceSettlementDb;
use App\Infrastructure\Billing\Payment\SepaBatchRepositoryDb;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Member;
use App\Models\PaymentInformation;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\SepaBatch;
use App\Models\User;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Override;
use Tests\FeatureTestCase;

// @mago-expect lint:kan-defect
final class SepaBatchRepositoryDbTest extends FeatureTestCase
{
    private SepaBatchRepositoryDb $repository;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new SepaBatchRepositoryDb(
            new MandateSequenceResolver(),
            new CreditInvoiceSettlementDb(),
        );
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

    public function test_add_open_invoices_from_batch_month_keeps_transfer_credits_but_skips_transfer_debits(): void
    {
        $batch = SepaBatch::factory()->createQuietly(['invoice_date' => '2026-05-13']);

        $transfer = Member::factory()->transfer()->createQuietly();
        PaymentInformation::factory()->createQuietly(['member_id' => $transfer->id]);
        $directDebit = Member::factory()->createQuietly();

        $transferDebit = Invoice::factory()
            ->forMember($transfer)
            ->createQuietly(['status' => InvoiceStatus::Open, 'date' => '2026-04-10']);
        InvoiceLine::factory()->createQuietly([
            'invoice_id' => $transferDebit->id,
            'price' => 25.00,
            'quantity' => 1,
        ]);

        $transferCredit = Invoice::factory()
            ->forMember($transfer)
            ->createQuietly(['status' => InvoiceStatus::Open, 'date' => '2026-04-10']);
        InvoiceLine::factory()->createQuietly([
            'invoice_id' => $transferCredit->id,
            'price' => -25.00,
            'quantity' => 1,
        ]);

        $directDebitDebit = Invoice::factory()
            ->forMember($directDebit)
            ->createQuietly(['status' => InvoiceStatus::Open, 'date' => '2026-04-10']);
        InvoiceLine::factory()->createQuietly([
            'invoice_id' => $directDebitDebit->id,
            'price' => 25.00,
            'quantity' => 1,
        ]);

        $purchaseOrder = PurchaseOrder::factory()
            ->accepted()
            ->createQuietly(['date' => '2026-04-10']);

        $attachedCount = $this->repository->addOpenInvoicesFromBatchMonth(SepaBatchId::create($batch->id));

        static::assertSame(2, $attachedCount);
        static::assertNull($transferDebit->refresh()->sepa_batch_id);
        static::assertSame($batch->id, $transferCredit->refresh()->sepa_batch_id);
        static::assertSame($batch->id, $directDebitDebit->refresh()->sepa_batch_id);

        $this->repository->addOpenPurchaseOrdersFromBatchMonth(SepaBatchId::create($batch->id));

        static::assertSame($batch->id, $purchaseOrder->refresh()->sepa_batch_id);
    }

    public function test_add_open_purchase_orders_from_batch_month_attaches_accepted_open_orders(): void
    {
        $batch = SepaBatch::factory()->create(['invoice_date' => '2026-05-13']);

        $purchaseOrders = PurchaseOrder::factory()
            ->accepted()
            ->createManyQuietly([
                ['date' => '2026-04-10'],
                ['date' => '2026-05-10'],
            ]);

        $attachedCount = $this->repository->addOpenPurchaseOrdersFromBatchMonth(SepaBatchId::create($batch->id));

        static::assertSame(2, $attachedCount);

        foreach ($purchaseOrders as $purchaseOrder) {
            $purchaseOrder->refresh();
            static::assertSame($batch->id, $purchaseOrder->sepa_batch_id);
            static::assertSame(PurchaseOrderStatus::Pending, $purchaseOrder->status);
            static::assertSame(PurchaseOrderApprovalStatus::Accepted, $purchaseOrder->approval_status);
        }
    }

    public function test_add_open_purchase_orders_from_batch_month_ignores_ineligible_purchase_orders(): void
    {
        $batch = SepaBatch::factory()->create(['invoice_date' => '2026-05-13']);
        $otherBatch = SepaBatch::factory()->create();

        $notAccepted = PurchaseOrder::factory()->open()->createQuietly(['date' => '2026-04-10']);
        $alreadyPending = PurchaseOrder::factory()->pending()->createQuietly(['date' => '2026-04-10']);
        $alreadyAttached = PurchaseOrder::factory()
            ->accepted()
            ->createQuietly([
                'date' => '2026-04-10',
                'sepa_batch_id' => $otherBatch->id,
            ]);
        $outsideBatchMonth = PurchaseOrder::factory()->accepted()->createQuietly(['date' => '2026-06-01']);

        $attachedCount = $this->repository->addOpenPurchaseOrdersFromBatchMonth(SepaBatchId::create($batch->id));

        static::assertSame(0, $attachedCount);

        static::assertSame(PurchaseOrderApprovalStatus::Pending, $notAccepted->refresh()->approval_status);
        static::assertNull($notAccepted->sepa_batch_id);
        static::assertNull($alreadyPending->refresh()->sepa_batch_id);
        static::assertSame($otherBatch->id, $alreadyAttached->refresh()->sepa_batch_id);
        static::assertNull($outsideBatchMonth->refresh()->sepa_batch_id);
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
            ->createQuietly(['status' => InvoiceStatus::Pending]);

        InvoiceLine::factory()
            ->state(['invoice_id' => $invoice->id])
            ->createQuietly(['price' => 10.00, 'quantity' => 2, 'vat' => 4.20]);

        $result = $this->repository->getInvoicesForExport(SepaBatchId::create($batch->id));

        static::assertCount(1, $result->invoices);
        static::assertSame($invoice->invoice_number, $result->invoices[0]->invoiceNumber);
        static::assertSame($member->name, $result->invoices[0]->recipientName);
        static::assertSame('NL91ABNA0417164300', $result->invoices[0]->iban);
        static::assertSame('ABNANL2A', $result->invoices[0]->bic);
        static::assertSame('C000001-000001', $result->invoices[0]->mandateId->value);
        static::assertSame(1, $result->invoices[0]->paymentInformationId->value);
        static::assertNull($result->invoices[0]->lastCollectedAt);
        static::assertGreaterThan(0.0, $result->invoices[0]->total->price);
        static::assertGreaterThan(0.0, $result->invoices[0]->total->vat);
        static::assertSame(
            (int) round($result->invoices[0]->total->price * 100),
            $result->invoices[0]->amountInCents(),
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

    public function test_attach_invoices_attaches_open_unbatched_invoices(): void
    {
        $batch = SepaBatch::factory()->createQuietly(['status' => SepaBatchStatus::Open]);
        $invoices = Invoice::factory()->createManyQuietly([
            ['status' => InvoiceStatus::Open, 'sepa_batch_id' => null],
            ['status' => InvoiceStatus::Open, 'sepa_batch_id' => null],
        ]);

        $count = $this->repository->attachInvoices(
            SepaBatchId::create($batch->id),
            new InvoiceIdList($invoices->map(static fn (Invoice $invoice): InvoiceId => InvoiceId::create($invoice->id))->all()),
        );

        static::assertSame(2, $count);

        foreach ($invoices as $invoice) {
            $this->assertDatabaseHas('invoices', [
                'id' => $invoice->id,
                'sepa_batch_id' => $batch->id,
            ]);
        }
    }

    public function test_attach_invoices_ignores_batched_and_non_open_invoices(): void
    {
        $batch = SepaBatch::factory()->createQuietly(['status' => SepaBatchStatus::Open]);
        $otherBatch = SepaBatch::factory()->createQuietly();
        $invoices = Invoice::factory()->createManyQuietly([
            ['status' => InvoiceStatus::Open, 'sepa_batch_id' => $otherBatch->id],
            ['status' => InvoiceStatus::Pending, 'sepa_batch_id' => null],
            ['status' => InvoiceStatus::Declined, 'sepa_batch_id' => null],
        ]);

        $count = $this->repository->attachInvoices(
            SepaBatchId::create($batch->id),
            new InvoiceIdList($invoices->map(static fn (Invoice $invoice): InvoiceId => InvoiceId::create($invoice->id))->all()),
        );

        static::assertSame(0, $count);

        foreach ($invoices as $invoice) {
            $this->assertDatabaseMissing('invoices', [
                'id' => $invoice->id,
                'sepa_batch_id' => $batch->id,
            ]);
        }
    }

    public function test_attach_invoices_throws_for_a_non_open_batch(): void
    {
        $batch = SepaBatch::factory()->createQuietly(['status' => SepaBatchStatus::Pending]);
        $invoice = Invoice::factory()->createQuietly([
            'status' => InvoiceStatus::Open,
            'sepa_batch_id' => null,
        ]);

        $this->expectException(DomainException::class);

        $this->repository->attachInvoices(
            SepaBatchId::create($batch->id),
            new InvoiceIdList([InvoiceId::create($invoice->id)]),
        );
    }

    public function test_get_invoices_for_export_excludes_open_paid_and_declined_invoices(): void
    {
        $batch = SepaBatch::factory()->create();
        $member = Member::factory()->createQuietly();

        PaymentInformation::factory()->createQuietly([
            'member_id' => $member->id,
            'banking_account_number' => 'NL91ABNA0417164300',
        ]);

        foreach ([InvoiceStatus::Pending, InvoiceStatus::Open, InvoiceStatus::Paid, InvoiceStatus::Declined] as $status) {
            $invoice = Invoice::factory()
                ->forMember($member)
                ->forBatch($batch)
                ->createQuietly(['status' => $status]);

            InvoiceLine::factory()
                ->state(['invoice_id' => $invoice->id])
                ->createQuietly(['price' => 10.00, 'quantity' => 1, 'vat' => 2.10]);
        }

        $result = $this->repository->getInvoicesForExport(SepaBatchId::create($batch->id));

        static::assertCount(1, $result->invoices);
    }

    public function test_get_collection_candidates_returns_open_and_pending_invoices_with_totals(): void
    {
        $batch = SepaBatch::factory()->create();

        $openInvoice = Invoice::factory()
            ->forBatch($batch)
            ->createQuietly(['status' => InvoiceStatus::Open]);
        InvoiceLine::factory()
            ->state(['invoice_id' => $openInvoice->id])
            ->createQuietly(['price' => 10.00, 'quantity' => 2, 'vat' => 2.10]);

        $pendingInvoice = Invoice::factory()
            ->forBatch($batch)
            ->createQuietly(['status' => InvoiceStatus::Pending]);
        InvoiceLine::factory()
            ->state(['invoice_id' => $pendingInvoice->id])
            ->createQuietly(['price' => 5.00, 'quantity' => 3, 'vat' => 1.05]);

        $result = $this->repository->getCollectionCandidates(SepaBatchId::create($batch->id));

        static::assertCount(2, $result);

        $totals = [];

        foreach ($result as $candidate) {
            $totals[$candidate->invoiceId->value] = [
                'status' => $candidate->status,
                'price' => $candidate->total->price,
                'vat' => $candidate->total->vat,
            ];
        }

        static::assertSame(InvoiceStatus::Open, $totals[$openInvoice->id]['status']);
        static::assertEqualsWithDelta(20.0, $totals[$openInvoice->id]['price'], 0.001);
        static::assertEqualsWithDelta(4.2, $totals[$openInvoice->id]['vat'], 0.001);

        static::assertSame(InvoiceStatus::Pending, $totals[$pendingInvoice->id]['status']);
        static::assertEqualsWithDelta(15.0, $totals[$pendingInvoice->id]['price'], 0.001);
        static::assertEqualsWithDelta(3.15, $totals[$pendingInvoice->id]['vat'], 0.001);
    }

    public function test_get_collection_candidates_includes_an_invoice_without_payment_information(): void
    {
        $batch = SepaBatch::factory()->create();
        $member = Member::factory()->createQuietly();

        $invoice = Invoice::factory()
            ->forMember($member)
            ->forBatch($batch)
            ->createQuietly(['status' => InvoiceStatus::Pending]);

        InvoiceLine::factory()
            ->state(['invoice_id' => $invoice->id])
            ->createQuietly(['price' => 10.00, 'quantity' => 1, 'vat' => 2.10]);

        $result = $this->repository->getCollectionCandidates(SepaBatchId::create($batch->id));

        static::assertCount(1, $result);
        static::assertSame($invoice->id, $result[0]->invoiceId->value);
        static::assertSame($member->id, $result[0]->memberId?->value);
    }

    public function test_get_collection_candidates_returns_a_zero_total_for_an_invoice_without_lines(): void
    {
        $batch = SepaBatch::factory()->create();

        $invoice = Invoice::factory()
            ->forBatch($batch)
            ->createQuietly(['status' => InvoiceStatus::Pending]);

        $result = $this->repository->getCollectionCandidates(SepaBatchId::create($batch->id));

        static::assertCount(1, $result);
        static::assertSame($invoice->id, $result[0]->invoiceId->value);
        static::assertSame(0.0, $result[0]->total->price);
        static::assertSame(0.0, $result[0]->total->vat);
    }

    public function test_get_collection_candidates_excludes_paid_and_declined_invoices(): void
    {
        $batch = SepaBatch::factory()->create();

        Invoice::factory()
            ->forBatch($batch)
            ->createQuietly(['status' => InvoiceStatus::Paid]);
        Invoice::factory()
            ->forBatch($batch)
            ->createQuietly(['status' => InvoiceStatus::Declined]);

        static::assertSame([], $this->repository->getCollectionCandidates(SepaBatchId::create($batch->id)));
    }

    public function test_get_export_context_returns_status_transfer_date_and_close_moment(): void
    {
        $batch = SepaBatch::factory()->createQuietly([
            'status' => SepaBatchStatus::Pending,
            'sepa_transfer_date' => '2026-11-05',
            'closed_at' => '2026-10-22 12:00:00',
        ]);

        $result = $this->repository->getExportContext(SepaBatchId::create($batch->id));

        static::assertSame(SepaBatchStatus::Pending, $result->status);
        static::assertSame('2026-11-05', $result->sepaTransferDate->format('Y-m-d'));
        static::assertSame('2026-10-22 12:00:00', $result->closedAt?->format('Y-m-d H:i:s'));
    }

    public function test_close_batch_stamps_closed_at(): void
    {
        $batch = SepaBatch::factory()->create(['status' => SepaBatchStatus::Open]);

        $this->repository->closeBatch(SepaBatchId::create($batch->id));

        static::assertNotNull($batch->fresh()->closed_at);
    }

    public function test_record_export_stamps_exported_at_and_persists_the_override(): void
    {
        $batch = SepaBatch::factory()->createQuietly();
        $invoice = Invoice::factory()->forBatch($batch)->createQuietly();
        $user = User::factory()->createQuietly();
        $exportedAt = CarbonImmutable::parse('2026-10-22 12:34:56');

        $this->repository->recordExport(
            SepaBatchId::create($batch->id),
            new ExportConfirmation(
                overrides: [
                    new ExportOverride(
                        invoiceId: InvoiceId::create($invoice->id),
                        reason: ExportReadinessReason::ExpiredMandate,
                        note: 'Written confirmation received.',
                    ),
                ],
            ),
            $user->id,
            $exportedAt,
        );

        $this->assertDatabaseHas('sepa_batches', [
            'id' => $batch->id,
            'exported_at' => '2026-10-22 12:34:56',
        ]);

        $this->assertDatabaseHas('sepa_export_overrides', [
            'sepa_batch_id' => $batch->id,
            'invoice_id' => $invoice->id,
            'reason' => ExportReadinessReason::ExpiredMandate->value,
            'note' => 'Written confirmation received.',
            'exported_at' => '2026-10-22 12:34:56',
            'exported_by' => $user->id,
        ]);
    }

    public function test_record_export_appends_a_new_override_on_every_export(): void
    {
        $batch = SepaBatch::factory()->createQuietly();
        $invoice = Invoice::factory()->forBatch($batch)->createQuietly();

        $confirmation = new ExportConfirmation(
            overrides: [
                new ExportOverride(
                    invoiceId: InvoiceId::create($invoice->id),
                    reason: ExportReadinessReason::ExpiredMandate,
                    note: 'First export.',
                ),
            ],
        );

        $this->repository->recordExport(
            SepaBatchId::create($batch->id),
            $confirmation,
            null,
            CarbonImmutable::parse('2026-10-22 12:00:00'),
        );
        $this->repository->recordExport(
            SepaBatchId::create($batch->id),
            $confirmation,
            null,
            CarbonImmutable::parse('2026-10-23 12:00:00'),
        );

        $this->assertDatabaseCount('sepa_export_overrides', 2);
        $this->assertDatabaseHas('sepa_batches', [
            'id' => $batch->id,
            'exported_at' => '2026-10-23 12:00:00',
        ]);
    }

    public function test_record_export_without_overrides_only_stamps_the_batch(): void
    {
        $batch = SepaBatch::factory()->createQuietly();
        $exportedAt = CarbonImmutable::parse('2026-10-22 12:00:00');

        $this->repository->recordExport(
            SepaBatchId::create($batch->id),
            new ExportConfirmation(),
            null,
            $exportedAt,
        );

        $this->assertDatabaseHas('sepa_batches', [
            'id' => $batch->id,
            'exported_at' => '2026-10-22 12:00:00',
        ]);
        $this->assertDatabaseCount('sepa_export_overrides', 0);
    }

    public function test_mark_submitted_stamps_the_submitting_user_and_time(): void
    {
        $batch = SepaBatch::factory()->createQuietly();
        $user = User::factory()->createQuietly();

        $this->repository->markSubmitted(
            SepaBatchId::create($batch->id),
            $user->id,
            CarbonImmutable::parse('2026-10-23 09:30:00'),
        );

        $this->assertDatabaseHas('sepa_batches', [
            'id' => $batch->id,
            'submitted_at' => '2026-10-23 09:30:00',
            'submitted_by' => $user->id,
        ]);
    }

    public function test_mark_submitted_does_not_overwrite_an_existing_submission(): void
    {
        $batch = SepaBatch::factory()->createQuietly();
        $firstUser = User::factory()->createQuietly();
        $secondUser = User::factory()->createQuietly();

        $this->repository->markSubmitted(
            SepaBatchId::create($batch->id),
            $firstUser->id,
            CarbonImmutable::parse('2026-10-23 09:30:00'),
        );
        $this->repository->markSubmitted(
            SepaBatchId::create($batch->id),
            $secondUser->id,
            CarbonImmutable::parse('2026-10-24 09:30:00'),
        );

        $this->assertDatabaseHas('sepa_batches', [
            'id' => $batch->id,
            'submitted_at' => '2026-10-23 09:30:00',
            'submitted_by' => $firstUser->id,
        ]);
    }

    public function test_get_invoices_for_export_uses_only_the_active_mandate_for_a_collection(): void
    {
        $batch = SepaBatch::factory()->createQuietly();
        $member = Member::factory()->createQuietly();

        PaymentInformation::factory()
            ->revoked(CarbonImmutable::parse('2025-01-01'))
            ->createQuietly([
                'member_id' => $member->id,
                'banking_account_number' => 'NL91ABNA0417164300',
            ]);
        $active = PaymentInformation::factory()->createQuietly([
            'member_id' => $member->id,
            'banking_account_number' => 'NL20RABO0123456789',
        ]);

        $invoice = Invoice::factory()
            ->forMember($member)
            ->forBatch($batch)
            ->createQuietly(['status' => InvoiceStatus::Pending]);
        InvoiceLine::factory()->createQuietly([
            'invoice_id' => $invoice->id,
            'price' => 10.00,
            'quantity' => 1,
        ]);

        $result = $this->repository->getInvoicesForExport(SepaBatchId::create($batch->id));

        static::assertCount(1, $result->invoices);
        static::assertSame('NL20RABO0123456789', $result->invoices[0]->iban);
        static::assertSame($active->id, $result->invoices[0]->paymentInformationId->value);
    }

    public function test_get_invoices_for_export_does_not_duplicate_a_member_with_mandate_history(): void
    {
        $batch = SepaBatch::factory()->createQuietly();
        $member = Member::factory()->createQuietly();

        PaymentInformation::factory()
            ->revoked(CarbonImmutable::parse('2025-01-01'))
            ->createQuietly(['member_id' => $member->id]);
        PaymentInformation::factory()->createQuietly(['member_id' => $member->id]);

        $invoice = Invoice::factory()
            ->forMember($member)
            ->forBatch($batch)
            ->createQuietly(['status' => InvoiceStatus::Pending]);
        InvoiceLine::factory()->createQuietly([
            'invoice_id' => $invoice->id,
            'price' => 10.00,
            'quantity' => 1,
        ]);

        $result = $this->repository->getInvoicesForExport(SepaBatchId::create($batch->id));

        static::assertCount(1, $result->invoices);
    }

    public function test_get_invoices_for_export_excludes_a_collection_without_an_active_mandate(): void
    {
        $batch = SepaBatch::factory()->createQuietly();
        $member = Member::factory()->createQuietly();

        PaymentInformation::factory()
            ->revoked(CarbonImmutable::parse('2025-01-01'))
            ->createQuietly(['member_id' => $member->id]);

        $invoice = Invoice::factory()
            ->forMember($member)
            ->forBatch($batch)
            ->createQuietly(['status' => InvoiceStatus::Pending]);
        InvoiceLine::factory()->createQuietly([
            'invoice_id' => $invoice->id,
            'price' => 10.00,
            'quantity' => 1,
        ]);

        $result = $this->repository->getInvoicesForExport(SepaBatchId::create($batch->id));

        static::assertSame([], $result->invoices);
    }

    public function test_get_invoices_for_export_uses_the_latest_mandate_for_a_refund_after_revocation(): void
    {
        $batch = SepaBatch::factory()->createQuietly();
        $member = Member::factory()->createQuietly();

        PaymentInformation::factory()
            ->revoked(CarbonImmutable::parse('2025-01-01'))
            ->createQuietly([
                'member_id' => $member->id,
                'banking_account_number' => 'NL91ABNA0417164300',
            ]);
        $latest = PaymentInformation::factory()
            ->revoked(CarbonImmutable::parse('2026-01-01'))
            ->createQuietly([
                'member_id' => $member->id,
                'banking_account_number' => 'NL44RABO0123456789',
            ]);

        $invoice = Invoice::factory()
            ->forMember($member)
            ->forBatch($batch)
            ->createQuietly(['status' => InvoiceStatus::Pending]);
        InvoiceLine::factory()->createQuietly([
            'invoice_id' => $invoice->id,
            'price' => -10.00,
            'quantity' => 1,
        ]);

        $result = $this->repository->getInvoicesForExport(SepaBatchId::create($batch->id));

        static::assertCount(1, $result->invoices);
        static::assertSame('NL44RABO0123456789', $result->invoices[0]->iban);
        static::assertSame($latest->id, $result->invoices[0]->paymentInformationId->value);
    }

    public function test_get_invoices_for_export_includes_a_manually_added_transfer_debit_with_an_active_mandate(): void
    {
        $batch = SepaBatch::factory()->createQuietly();
        $member = Member::factory()->transfer()->createQuietly();

        PaymentInformation::factory()->createQuietly([
            'member_id' => $member->id,
            'banking_account_number' => 'NL91ABNA0417164300',
        ]);

        $invoice = Invoice::factory()
            ->forMember($member)
            ->forBatch($batch)
            ->createQuietly(['status' => InvoiceStatus::Pending]);
        InvoiceLine::factory()->createQuietly([
            'invoice_id' => $invoice->id,
            'price' => 10.00,
            'quantity' => 1,
        ]);

        $result = $this->repository->getInvoicesForExport(SepaBatchId::create($batch->id));

        static::assertCount(1, $result->invoices);
        static::assertSame($invoice->id, $result->invoices[0]->invoiceId->value);
    }

    public function test_get_invoices_for_export_drops_a_refund_with_a_malformed_iban(): void
    {
        $batch = SepaBatch::factory()->createQuietly();
        $member = Member::factory()->createQuietly();

        PaymentInformation::factory()
            ->revoked(CarbonImmutable::parse('2026-01-01'))
            ->createQuietly([
                'member_id' => $member->id,
                'banking_account_number' => 'NOT-AN-IBAN',
            ]);

        $invoice = Invoice::factory()
            ->forMember($member)
            ->forBatch($batch)
            ->createQuietly(['status' => InvoiceStatus::Pending]);
        InvoiceLine::factory()->createQuietly([
            'invoice_id' => $invoice->id,
            'price' => -10.00,
            'quantity' => 1,
        ]);

        $result = $this->repository->getInvoicesForExport(SepaBatchId::create($batch->id));

        static::assertSame([], $result->invoices);
    }

    public function test_get_invoices_for_export_exposes_the_mandate_id_and_last_collected_at(): void
    {
        $batch = SepaBatch::factory()->createQuietly();
        $member = Member::factory()->createQuietly();
        $collectedAt = CarbonImmutable::parse('2026-09-30 00:00:00');

        $mandate = PaymentInformation::factory()
            ->collectedAt($collectedAt)
            ->createQuietly(['member_id' => $member->id]);

        $invoice = Invoice::factory()
            ->forMember($member)
            ->forBatch($batch)
            ->createQuietly(['status' => InvoiceStatus::Pending]);
        InvoiceLine::factory()->createQuietly([
            'invoice_id' => $invoice->id,
            'price' => 10.00,
            'quantity' => 1,
        ]);

        $result = $this->repository->getInvoicesForExport(SepaBatchId::create($batch->id));

        static::assertCount(1, $result->invoices);
        static::assertSame($mandate->id, $result->invoices[0]->paymentInformationId->value);
        static::assertSame('2026-09-30', $result->invoices[0]->lastCollectedAt?->format('Y-m-d'));
    }

    public function test_get_export_context_exposes_the_submission_moment(): void
    {
        $batch = SepaBatch::factory()->createQuietly([
            'status' => SepaBatchStatus::Pending,
            'submitted_at' => '2026-10-23 09:30:00',
        ]);

        $result = $this->repository->getExportContext(SepaBatchId::create($batch->id));

        static::assertSame('2026-10-23 09:30:00', $result->submittedAt?->format('Y-m-d H:i:s'));
    }

    public function test_get_invoices_for_export_returns_first_for_a_never_collected_mandate(): void
    {
        [$batch, $invoice] = $this->exportableScenario();

        PaymentInformation::factory()->createQuietly(['member_id' => $invoice->member_id]);

        $result = $this->repository->getInvoicesForExport(SepaBatchId::create($batch->id));

        static::assertCount(1, $result->invoices);
        static::assertSame(SequenceType::First, $result->invoices[0]->sequenceType);
    }

    public function test_get_invoices_for_export_returns_recurring_for_a_collected_mandate(): void
    {
        $batch = SepaBatch::factory()->createQuietly();
        $member = Member::factory()->createQuietly();
        $invoice = $this->pendingInvoice($batch, $member);

        PaymentInformation::factory()
            ->collectedAt(CarbonImmutable::parse('2026-09-30 00:00:00'))
            ->createQuietly(['member_id' => $member->id]);

        $result = $this->repository->getInvoicesForExport(SepaBatchId::create($batch->id));

        static::assertCount(1, $result->invoices);
        static::assertSame(SequenceType::Recurring, $result->invoices[0]->sequenceType);
        static::assertSame($invoice->id, $result->invoices[0]->invoiceId->value);
    }

    public function test_get_invoices_for_export_prefers_the_frozen_plan_over_the_mandate(): void
    {
        $batch = SepaBatch::factory()->createQuietly();
        $member = Member::factory()->createQuietly();
        $this->pendingInvoice($batch, $member, SequenceType::First);

        PaymentInformation::factory()
            ->collectedAt(CarbonImmutable::parse('2026-09-30 00:00:00'))
            ->createQuietly(['member_id' => $member->id]);

        $result = $this->repository->getInvoicesForExport(SepaBatchId::create($batch->id));

        static::assertCount(1, $result->invoices);
        static::assertSame(SequenceType::First, $result->invoices[0]->sequenceType);
    }

    public function test_get_invoices_for_export_keeps_first_after_the_batch_is_submitted(): void
    {
        $batch = SepaBatch::factory()->createQuietly();
        $member = Member::factory()->createQuietly();
        $this->pendingInvoice($batch, $member, SequenceType::First);

        $submittedAt = CarbonImmutable::parse('2026-10-23 09:30:00');

        PaymentInformation::factory()
            ->collectedAt($submittedAt)
            ->createQuietly(['member_id' => $member->id]);

        $batch->update(['submitted_at' => $submittedAt]);

        $result = $this->repository->getInvoicesForExport(SepaBatchId::create($batch->id));

        static::assertCount(1, $result->invoices);
        static::assertSame(SequenceType::First, $result->invoices[0]->sequenceType);
    }

    public function test_get_invoices_for_export_uses_the_new_active_mandate_after_reauthorisation(): void
    {
        $batch = SepaBatch::factory()->createQuietly();
        $member = Member::factory()->createQuietly();
        $this->pendingInvoice($batch, $member);

        PaymentInformation::factory()
            ->collectedAt(CarbonImmutable::parse('2026-09-30 00:00:00'))
            ->revoked(CarbonImmutable::parse('2026-10-01 00:00:00'))
            ->createQuietly(['member_id' => $member->id]);
        PaymentInformation::factory()->createQuietly(['member_id' => $member->id]);

        $result = $this->repository->getInvoicesForExport(SepaBatchId::create($batch->id));

        static::assertCount(1, $result->invoices);
        static::assertSame(SequenceType::First, $result->invoices[0]->sequenceType);
    }

    public function test_get_collection_candidates_returns_the_resolved_sequence(): void
    {
        $batch = SepaBatch::factory()->createQuietly();
        $neverCollected = Member::factory()->createQuietly();
        $collected = Member::factory()->createQuietly();

        $firstInvoice = $this->pendingInvoice($batch, $neverCollected);
        $recurringInvoice = $this->pendingInvoice($batch, $collected);

        PaymentInformation::factory()->createQuietly(['member_id' => $neverCollected->id]);
        PaymentInformation::factory()
            ->collectedAt(CarbonImmutable::parse('2026-09-30 00:00:00'))
            ->createQuietly(['member_id' => $collected->id]);

        $result = $this->repository->getCollectionCandidates(SepaBatchId::create($batch->id));

        $sequences = [];

        foreach ($result as $candidate) {
            $sequences[$candidate->invoiceId->value] = $candidate->sequenceType;
        }

        static::assertSame(SequenceType::First, $sequences[$firstInvoice->id]);
        static::assertSame(SequenceType::Recurring, $sequences[$recurringInvoice->id]);
    }

    public function test_get_collection_candidates_prefers_the_frozen_plan(): void
    {
        $batch = SepaBatch::factory()->createQuietly();
        $member = Member::factory()->createQuietly();
        $this->pendingInvoice($batch, $member, SequenceType::First);

        PaymentInformation::factory()
            ->collectedAt(CarbonImmutable::parse('2026-09-30 00:00:00'))
            ->createQuietly(['member_id' => $member->id]);

        $result = $this->repository->getCollectionCandidates(SepaBatchId::create($batch->id));

        static::assertCount(1, $result);
        static::assertSame(SequenceType::First, $result[0]->sequenceType);
    }

    public function test_get_collection_candidates_does_not_fan_out_on_mandate_history(): void
    {
        $batch = SepaBatch::factory()->createQuietly();
        $member = Member::factory()->createQuietly();
        $this->pendingInvoice($batch, $member);

        PaymentInformation::factory()
            ->revoked(CarbonImmutable::parse('2025-01-01'))
            ->createQuietly(['member_id' => $member->id]);
        PaymentInformation::factory()->createQuietly(['member_id' => $member->id]);

        $result = $this->repository->getCollectionCandidates(SepaBatchId::create($batch->id));

        static::assertCount(1, $result);
    }

    public function test_freeze_sequence_types_stamps_first_and_recurring(): void
    {
        $batch = SepaBatch::factory()->createQuietly();
        $neverCollected = Member::factory()->createQuietly();
        $collected = Member::factory()->createQuietly();

        $firstInvoice = $this->pendingInvoice($batch, $neverCollected, null, InvoiceStatus::Open);
        $recurringInvoice = $this->pendingInvoice($batch, $collected, null, InvoiceStatus::Open);

        PaymentInformation::factory()->createQuietly(['member_id' => $neverCollected->id]);
        PaymentInformation::factory()
            ->collectedAt(CarbonImmutable::parse('2026-09-30 00:00:00'))
            ->createQuietly(['member_id' => $collected->id]);

        $this->repository->freezeSequenceTypes(SepaBatchId::create($batch->id));

        static::assertSame(SequenceType::First, $firstInvoice->refresh()->sepa_sequence_type);
        static::assertSame(SequenceType::Recurring, $recurringInvoice->refresh()->sepa_sequence_type);
    }

    public function test_freeze_sequence_types_does_not_overwrite_an_existing_plan(): void
    {
        $batch = SepaBatch::factory()->createQuietly();
        $member = Member::factory()->createQuietly();

        $invoice = $this->pendingInvoice($batch, $member, SequenceType::Recurring, InvoiceStatus::Open);

        PaymentInformation::factory()->createQuietly(['member_id' => $member->id]);

        $this->repository->freezeSequenceTypes(SepaBatchId::create($batch->id));

        static::assertSame(SequenceType::Recurring, $invoice->refresh()->sepa_sequence_type);
    }

    public function test_freeze_sequence_types_skips_a_refund(): void
    {
        $batch = SepaBatch::factory()->createQuietly();
        $member = Member::factory()->createQuietly();

        $invoice = $this->pendingInvoice($batch, $member, null, InvoiceStatus::Open, -10.0);

        PaymentInformation::factory()->createQuietly(['member_id' => $member->id]);

        $this->repository->freezeSequenceTypes(SepaBatchId::create($batch->id));

        static::assertNull($invoice->refresh()->sepa_sequence_type);
    }

    public function test_freeze_sequence_types_skips_a_declined_invoice(): void
    {
        $batch = SepaBatch::factory()->createQuietly();
        $member = Member::factory()->createQuietly();

        $invoice = $this->pendingInvoice($batch, $member, null, InvoiceStatus::Declined);

        PaymentInformation::factory()->createQuietly(['member_id' => $member->id]);

        $this->repository->freezeSequenceTypes(SepaBatchId::create($batch->id));

        static::assertNull($invoice->refresh()->sepa_sequence_type);
    }

    public function test_replan_sequence_types_clears_and_refreezes_the_batch(): void
    {
        $batch = SepaBatch::factory()->createQuietly([
            'status' => SepaBatchStatus::Pending,
            'exported_at' => null,
        ]);
        $member = Member::factory()->createQuietly();
        $invoice = $this->pendingInvoice($batch, $member, SequenceType::First);

        PaymentInformation::factory()
            ->collectedAt(CarbonImmutable::parse('2026-09-30 00:00:00'))
            ->createQuietly(['member_id' => $member->id]);

        $this->repository->replanSequenceTypes(SepaBatchId::create($batch->id));

        static::assertSame(SequenceType::Recurring, $invoice->refresh()->sepa_sequence_type);
    }

    public function test_replan_sequence_types_uses_the_current_active_mandate(): void
    {
        $batch = SepaBatch::factory()->createQuietly([
            'status' => SepaBatchStatus::Pending,
            'exported_at' => null,
        ]);
        $member = Member::factory()->createQuietly();
        $invoice = $this->pendingInvoice($batch, $member, SequenceType::Recurring);

        PaymentInformation::factory()
            ->collectedAt(CarbonImmutable::parse('2026-01-01 00:00:00'))
            ->revoked(CarbonImmutable::parse('2026-10-01 00:00:00'))
            ->createQuietly(['member_id' => $member->id]);
        PaymentInformation::factory()->createQuietly(['member_id' => $member->id]);

        $this->repository->replanSequenceTypes(SepaBatchId::create($batch->id));

        static::assertSame(SequenceType::First, $invoice->refresh()->sepa_sequence_type);
    }

    public function test_replan_sequence_types_rejects_an_exported_batch(): void
    {
        $batch = SepaBatch::factory()->createQuietly([
            'status' => SepaBatchStatus::Pending,
            'exported_at' => '2026-10-23 09:30:00',
        ]);

        $this->expectException(DomainException::class);

        $this->repository->replanSequenceTypes(SepaBatchId::create($batch->id));
    }

    public function test_replan_sequence_types_rejects_a_submitted_batch(): void
    {
        $batch = SepaBatch::factory()->createQuietly([
            'status' => SepaBatchStatus::Pending,
            'submitted_at' => '2026-10-23 09:30:00',
        ]);

        $this->expectException(DomainException::class);

        $this->repository->replanSequenceTypes(SepaBatchId::create($batch->id));
    }

    public function test_replan_sequence_types_rejects_a_completed_batch(): void
    {
        $batch = SepaBatch::factory()->createQuietly(['status' => SepaBatchStatus::Completed]);

        $this->expectException(DomainException::class);

        $this->repository->replanSequenceTypes(SepaBatchId::create($batch->id));
    }

    public function test_replan_sequence_types_does_not_touch_another_batch(): void
    {
        $batch = SepaBatch::factory()->createQuietly([
            'status' => SepaBatchStatus::Pending,
            'exported_at' => null,
        ]);
        $otherBatch = SepaBatch::factory()->createQuietly();
        $member = Member::factory()->createQuietly();
        PaymentInformation::factory()->createQuietly(['member_id' => $member->id]);

        $this->pendingInvoice($batch, $member, SequenceType::Recurring);
        $otherInvoice = $this->pendingInvoice($otherBatch, $member, SequenceType::First);

        $this->repository->replanSequenceTypes(SepaBatchId::create($batch->id));

        static::assertSame(SequenceType::First, $otherInvoice->refresh()->sepa_sequence_type);
    }

    /** @return array{SepaBatch, Invoice, Member} */
    private function exportableScenario(): array
    {
        $batch = SepaBatch::factory()->createQuietly();
        $member = Member::factory()->createQuietly();

        return [$batch, $this->pendingInvoice($batch, $member), $member];
    }

    private function pendingInvoice(
        SepaBatch $batch,
        Member $member,
        ?SequenceType $frozenSequence = null,
        InvoiceStatus $status = InvoiceStatus::Pending,
        float $price = 10.0,
    ): Invoice {
        $invoice = Invoice::factory()
            ->forMember($member)
            ->forBatch($batch)
            ->createQuietly([
                'status' => $status,
                'sepa_sequence_type' => $frozenSequence,
            ]);

        InvoiceLine::factory()->createQuietly([
            'invoice_id' => $invoice->id,
            'price' => $price,
            'quantity' => 1,
        ]);

        return $invoice;
    }
}
