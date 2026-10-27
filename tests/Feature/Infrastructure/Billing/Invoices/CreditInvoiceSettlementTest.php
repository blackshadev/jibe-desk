<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Billing\Invoices;

use App\Domain\Billing\Invoices\InvoiceId;
use App\Domain\Billing\Invoices\InvoiceIdList;
use App\Domain\Billing\Invoices\InvoiceService;
use App\Domain\Billing\Invoices\InvoiceStatus;
use App\Domain\Billing\Invoices\Jobs\SendInvoiceEmail;
use App\Domain\Billing\Payment\SepaBatchId;
use App\Domain\Billing\Payment\SepaBatchService;
use App\Domain\Billing\Payment\SepaBatchStatus;
use App\Infrastructure\Billing\Payment\SepaBatchRepositoryDb;
use App\Models\Invoice;
use App\Models\InvoiceDecline;
use App\Models\InvoiceLine;
use App\Models\Member;
use App\Models\PaymentInformation;
use App\Models\SepaBatch;
use Illuminate\Support\Facades\Bus;
use Tests\FeatureTestCase;

final class CreditInvoiceSettlementTest extends FeatureTestCase
{
    public function test_batch_close_settles_a_credit_pair_that_reaches_pending_together(): void
    {
        [$member, $batch] = $this->memberAndOpenBatch();
        $original = $this->originalInvoice($member, InvoiceStatus::Pending, $batch);
        $credit = $this->creditInvoice($member, $original, InvoiceStatus::Open, $batch);

        $this->closeBatch($batch);

        $this->assertSettledAndDetached($original);
        $this->assertSettledAndDetached($credit);

        $this->assertDatabaseHas('bookkeeping_records', [
            'reference_type' => Invoice::class,
            'reference_id' => $original->id,
        ]);
        $this->assertDatabaseHas('bookkeeping_records', [
            'reference_type' => Invoice::class,
            'reference_id' => $credit->id,
        ]);

        static::assertSame([], $this->exportInvoiceIds($batch));
    }

    public function test_batch_close_keeps_the_credit_pending_when_the_original_is_in_an_exported_batch(): void
    {
        [$member, $batch] = $this->memberAndOpenBatch();
        $exportedBatch = SepaBatch::factory()->createQuietly([
            'status' => SepaBatchStatus::Pending,
            'exported_at' => '2026-10-01 12:00:00',
        ]);
        $original = $this->originalInvoice($member, InvoiceStatus::Pending, $exportedBatch);
        $credit = $this->creditInvoice($member, $original, InvoiceStatus::Open, $batch);

        $this->closeBatch($batch);

        static::assertSame(InvoiceStatus::Pending, $original->refresh()->status);
        static::assertSame(InvoiceStatus::Pending, $credit->refresh()->status);
        static::assertSame($batch->id, $credit->sepa_batch_id);
        static::assertSame([$credit->id], $this->exportInvoiceIds($batch));
    }

    public function test_batch_close_settles_a_declined_original_even_from_an_exported_batch(): void
    {
        [$member, $batch] = $this->memberAndOpenBatch();
        $exportedBatch = SepaBatch::factory()->createQuietly([
            'status' => SepaBatchStatus::Pending,
            'exported_at' => '2026-10-01 12:00:00',
        ]);
        $original = $this->originalInvoice($member, InvoiceStatus::Declined, $exportedBatch);
        $credit = $this->creditInvoice($member, $original, InvoiceStatus::Open, $batch);

        $this->closeBatch($batch);

        $this->assertSettledAndDetached($original);
        $this->assertSettledAndDetached($credit);
    }

    public function test_batch_close_does_not_settle_when_the_original_is_paid(): void
    {
        [$member, $batch] = $this->memberAndOpenBatch();
        $original = $this->originalInvoice($member, InvoiceStatus::Paid, null);
        $credit = $this->creditInvoice($member, $original, InvoiceStatus::Open, $batch);

        $this->closeBatch($batch);

        static::assertSame(InvoiceStatus::Paid, $original->refresh()->status);
        static::assertSame(InvoiceStatus::Pending, $credit->refresh()->status);
    }

    public function test_manual_mark_as_pending_settles_the_pair_without_queueing_mail(): void
    {
        Bus::fake();

        [$member] = $this->memberAndOpenBatch();
        $original = $this->originalInvoice($member, InvoiceStatus::Pending);
        $credit = $this->creditInvoice($member, $original, InvoiceStatus::Open);

        app(InvoiceService::class)->markAsPending(new InvoiceIdList([$this->invoiceId($credit)]));

        $this->assertSettledAndDetached($original);
        $this->assertSettledAndDetached($credit);

        Bus::assertNotDispatched(SendInvoiceEmail::class);
    }

    public function test_settlement_is_symmetric_when_the_original_reaches_pending_last(): void
    {
        Bus::fake();

        [$member] = $this->memberAndOpenBatch();
        $original = $this->originalInvoice($member, InvoiceStatus::Open);
        $credit = $this->creditInvoice($member, $original, InvoiceStatus::Pending);

        app(InvoiceService::class)->markAsPending(new InvoiceIdList([$this->invoiceId($original)]));

        $this->assertSettledAndDetached($original);
        $this->assertSettledAndDetached($credit);
    }

    public function test_settling_an_already_settled_pair_is_a_no_op(): void
    {
        [$member] = $this->memberAndOpenBatch();
        $original = $this->originalInvoice($member, InvoiceStatus::Settled);
        $credit = $this->creditInvoice($member, $original, InvoiceStatus::Settled);

        app(InvoiceService::class)->markAsPending(new InvoiceIdList([
            $this->invoiceId($original),
            $this->invoiceId($credit),
        ]));

        static::assertSame(InvoiceStatus::Settled, $original->refresh()->status);
        static::assertSame(InvoiceStatus::Settled, $credit->refresh()->status);
    }

    public function test_settlement_keeps_sent_at_and_decline_history(): void
    {
        [$member] = $this->memberAndOpenBatch();
        $sentAt = now()->subDay()->startOfSecond();
        $original = $this->originalInvoice($member, InvoiceStatus::Declined);
        $original->update(['sent_at' => $sentAt]);
        InvoiceDecline::factory()->createQuietly(['invoice_id' => $original->id]);
        $credit = $this->creditInvoice($member, $original, InvoiceStatus::Open);

        app(InvoiceService::class)->markAsPending(new InvoiceIdList([$this->invoiceId($credit)]));

        static::assertSame($sentAt->toDateTimeString(), $original->refresh()->sent_at?->toDateTimeString());
        static::assertSame(1, InvoiceDecline::query()->where('invoice_id', $original->id)->count());
    }

    public function test_batch_totals_and_counts_drop_the_settled_pair(): void
    {
        [$member, $batch] = $this->memberAndOpenBatch();
        $original = $this->originalInvoice($member, InvoiceStatus::Pending, $batch);
        $this->creditInvoice($member, $original, InvoiceStatus::Open, $batch);

        $this->closeBatch($batch);

        $batch->refresh();

        static::assertSame(0, $batch->invoice_count);
        static::assertSame(0, $batch->open_invoice_count);
        static::assertSame(0.0, $batch->open_invoice_total->price);
    }

    /** @return array{Member, SepaBatch} */
    private function memberAndOpenBatch(): array
    {
        $member = Member::factory()->createQuietly();
        PaymentInformation::factory()->createQuietly(['member_id' => $member->id]);

        return [$member, SepaBatch::factory()->createQuietly(['status' => SepaBatchStatus::Open])];
    }

    private function originalInvoice(
        Member $member,
        InvoiceStatus $status,
        ?SepaBatch $batch = null,
    ): Invoice {
        return Invoice::factory()
            ->forMember($member)
            ->has(InvoiceLine::factory()->state(['price' => 10.0, 'quantity' => 1]), 'lines')
            ->createQuietly([
                'status' => $status,
                'sepa_batch_id' => $batch?->id,
            ]);
    }

    private function creditInvoice(
        Member $member,
        Invoice $original,
        InvoiceStatus $status,
        ?SepaBatch $batch = null,
    ): Invoice {
        return Invoice::factory()
            ->forMember($member)
            ->has(InvoiceLine::factory()->state(['price' => -10.0, 'quantity' => 1]), 'lines')
            ->createQuietly([
                'status' => $status,
                'sepa_batch_id' => $batch?->id,
                'credit_invoice_id' => $original->id,
            ]);
    }

    private function closeBatch(SepaBatch $batch): void
    {
        app(SepaBatchService::class)->closeBatch(SepaBatchId::create($batch->id));
    }

    private function assertSettledAndDetached(Invoice $invoice): void
    {
        $invoice->refresh();

        static::assertSame(InvoiceStatus::Settled, $invoice->status);
        static::assertNull($invoice->sepa_batch_id);
        static::assertNull($invoice->sepa_sequence_type);
    }

    /** @return list<int> */
    private function exportInvoiceIds(SepaBatch $batch): array
    {
        return array_map(
            static fn ($invoice): int => $invoice->invoiceId->value,
            app(SepaBatchRepositoryDb::class)->getInvoicesForExport(SepaBatchId::create($batch->id))->invoices,
        );
    }

    private function invoiceId(Invoice $invoice): InvoiceId
    {
        return InvoiceId::create($invoice->id);
    }
}
