<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Billing\Invoices;

use App\Domain\Billing\Invoices\InvoiceId;
use App\Domain\Billing\Invoices\InvoiceIdList;
use App\Domain\Billing\Invoices\InvoiceStatus;
use App\Domain\Billing\Payment\SequenceType;
use App\Domain\Bookkeeping\Banking\BankTransactions\BankTransactionId;
use App\Infrastructure\Billing\Invoices\InvoiceRepositoryDb;
use App\Models\BankTransaction;
use App\Models\Invoice;
use App\Models\InvoiceDecline;
use App\Models\SepaBatch;
use Override;
use Tests\FeatureTestCase;

final class InvoiceRepositoryDbTransitionTest extends FeatureTestCase
{
    private InvoiceRepositoryDb $repository;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = app(InvoiceRepositoryDb::class);
    }

    public function test_mark_as_declined_records_the_source_batch_and_reversal_link(): void
    {
        $batch = SepaBatch::factory()->createQuietly();
        $transaction = BankTransaction::factory()->createQuietly();
        $invoice = Invoice::factory()->createQuietly([
            'status' => InvoiceStatus::Pending,
            'sepa_batch_id' => $batch->id,
        ]);

        $this->repository->markAsDeclined(
            new InvoiceIdList([InvoiceId::create($invoice->id)]),
            BankTransactionId::create($transaction->id),
            'Teruggeboekt',
        );

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => InvoiceStatus::Declined->value,
        ]);
        $this->assertDatabaseHas('invoice_declines', [
            'invoice_id' => $invoice->id,
            'sepa_batch_id' => $batch->id,
            'bank_transaction_id' => $transaction->id,
            'note' => 'Teruggeboekt',
        ]);
    }

    public function test_mark_as_declined_returns_only_transitioned_ids(): void
    {
        $pending = Invoice::factory()->createQuietly(['status' => InvoiceStatus::Pending]);
        $paid = Invoice::factory()->createQuietly(['status' => InvoiceStatus::Paid]);
        $declined = Invoice::factory()->createQuietly(['status' => InvoiceStatus::Declined]);

        $result = $this->repository->markAsDeclined(new InvoiceIdList([
            InvoiceId::create($pending->id),
            InvoiceId::create($paid->id),
            InvoiceId::create($declined->id),
        ]));

        static::assertSame([$pending->id], $result->values());
    }

    public function test_mark_as_declined_does_not_record_a_paid_invoice(): void
    {
        $paid = Invoice::factory()->createQuietly(['status' => InvoiceStatus::Paid]);

        $this->repository->markAsDeclined(new InvoiceIdList([InvoiceId::create($paid->id)]));

        static::assertSame(0, InvoiceDecline::query()->count());
        $this->assertDatabaseHas('invoices', [
            'id' => $paid->id,
            'status' => InvoiceStatus::Paid->value,
        ]);
    }

    public function test_reopen_returns_declined_invoices_to_open_and_detaches_them(): void
    {
        $batch = SepaBatch::factory()->createQuietly();
        $invoice = Invoice::factory()->createQuietly([
            'status' => InvoiceStatus::Declined,
            'sepa_batch_id' => $batch->id,
        ]);

        $this->repository->reopen(new InvoiceIdList([InvoiceId::create($invoice->id)]));

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => InvoiceStatus::Open->value,
            'sepa_batch_id' => null,
        ]);
    }

    public function test_reopen_clears_the_frozen_sequence_type(): void
    {
        $batch = SepaBatch::factory()->createQuietly();
        $invoice = Invoice::factory()->createQuietly([
            'status' => InvoiceStatus::Declined,
            'sepa_batch_id' => $batch->id,
            'sepa_sequence_type' => SequenceType::First,
        ]);

        $this->repository->reopen(new InvoiceIdList([InvoiceId::create($invoice->id)]));

        static::assertNull($invoice->refresh()->sepa_sequence_type);
    }

    public function test_reopen_clears_sent_at(): void
    {
        $invoice = Invoice::factory()->createQuietly([
            'status' => InvoiceStatus::Declined,
            'sent_at' => now(),
        ]);

        $this->repository->reopen(new InvoiceIdList([InvoiceId::create($invoice->id)]));

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'sent_at' => null,
        ]);
    }

    public function test_reopen_skips_invoices_with_a_credit_invoice(): void
    {
        $original = Invoice::factory()->createQuietly(['status' => InvoiceStatus::Pending]);
        $credit = Invoice::factory()->createQuietly([
            'status' => InvoiceStatus::Declined,
            'credit_invoice_id' => $original->id,
        ]);

        $this->repository->reopen(new InvoiceIdList([InvoiceId::create($credit->id)]));

        $this->assertDatabaseHas('invoices', [
            'id' => $credit->id,
            'status' => InvoiceStatus::Declined->value,
        ]);
    }

    public function test_repository_transitions_never_move_a_settled_invoice(): void
    {
        $settled = Invoice::factory()->createQuietly([
            'status' => InvoiceStatus::Settled,
            'sent_at' => now(),
        ]);

        $ids = new InvoiceIdList([InvoiceId::create($settled->id)]);

        $this->repository->markAsPaid($ids);
        $this->repository->markAsPending($ids);
        $this->repository->markAsPendingIfDeclined($ids);
        $this->repository->markAsDeclined($ids);
        $this->repository->reopen($ids);

        static::assertSame(InvoiceStatus::Settled, $settled->refresh()->status);
    }

    public function test_mark_as_pending_if_declined_leaves_other_statuses_untouched(): void
    {
        $declined = Invoice::factory()->createQuietly(['status' => InvoiceStatus::Declined]);
        $paid = Invoice::factory()->createQuietly(['status' => InvoiceStatus::Paid]);

        $this->repository->markAsPendingIfDeclined(new InvoiceIdList([
            InvoiceId::create($declined->id),
            InvoiceId::create($paid->id),
        ]));

        $this->assertDatabaseHas('invoices', [
            'id' => $declined->id,
            'status' => InvoiceStatus::Pending->value,
        ]);
        $this->assertDatabaseHas('invoices', [
            'id' => $paid->id,
            'status' => InvoiceStatus::Paid->value,
        ]);
    }
}
