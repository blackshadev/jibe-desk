<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\InvoiceBatches;

use App\Domain\Invoices\InvoiceBatchStatus;
use App\Domain\Invoices\InvoiceStatus;
use App\Models\Invoice;
use App\Models\InvoiceBatch;
use Tests\FeatureTestCase;

final class InvoiceBatchResourceTest extends FeatureTestCase
{
    public function test_batch_can_be_created(): void
    {
        $batch = InvoiceBatch::factory()->create();

        $this->assertDatabaseHas('invoice_batches', [
            'id' => $batch->id,
            'status' => InvoiceBatchStatus::Open->value,
        ]);
    }

    public function test_batch_can_be_created_with_open_status(): void
    {
        $batch = InvoiceBatch::factory()->create(['status' => InvoiceBatchStatus::Open]);

        static::assertSame(InvoiceBatchStatus::Open, $batch->status);
    }

    public function test_batch_can_be_marked_as_pending(): void
    {
        $batch = InvoiceBatch::factory()->create(['status' => InvoiceBatchStatus::Open]);
        $batch->update(['status' => InvoiceBatchStatus::Pending]);

        $this->assertDatabaseHas('invoice_batches', [
            'id' => $batch->id,
            'status' => InvoiceBatchStatus::Pending->value,
        ]);
    }

    public function test_batch_can_be_completed(): void
    {
        $batch = InvoiceBatch::factory()->create(['status' => InvoiceBatchStatus::Pending]);
        $batch->update(['status' => InvoiceBatchStatus::Completed]);

        $this->assertDatabaseHas('invoice_batches', [
            'id' => $batch->id,
            'status' => InvoiceBatchStatus::Completed->value,
        ]);
    }

    public function test_invoices_can_be_attached_to_batch(): void
    {
        $batch = InvoiceBatch::factory()->create();
        $invoice = Invoice::factory()->createQuietly([
            'status' => InvoiceStatus::Open,
            'invoice_batch_id' => null,
        ]);

        $invoice->update(['invoice_batch_id' => $batch->id]);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'invoice_batch_id' => $batch->id,
        ]);
    }

    public function test_invoices_can_be_detached_from_batch(): void
    {
        $batch = InvoiceBatch::factory()->create();
        $invoice = Invoice::factory()
            ->forBatch($batch)
            ->createQuietly(['status' => InvoiceStatus::Open]);

        $invoice->update(['invoice_batch_id' => null]);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'invoice_batch_id' => null,
        ]);
    }

    public function test_invoice_status_can_be_updated_to_paid(): void
    {
        $invoice = Invoice::factory()->createQuietly(['status' => InvoiceStatus::Open]);

        $invoice->update(['status' => InvoiceStatus::Paid]);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => InvoiceStatus::Paid->value,
        ]);
    }

    public function test_invoice_status_can_be_updated_to_declined(): void
    {
        $invoice = Invoice::factory()->createQuietly(['status' => InvoiceStatus::Open]);

        $invoice->update(['status' => InvoiceStatus::Declined]);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => InvoiceStatus::Declined->value,
        ]);
    }

    public function test_batch_total_can_be_calculated(): void
    {
        $batch = InvoiceBatch::factory()->create();

        $invoice = Invoice::factory()
            ->forBatch($batch)
            ->withLines(1)
            ->createQuietly();

        $batch->load('invoices.lines');

        static::assertGreaterThan(0.0, $batch->total->price);
    }

    public function test_open_total_filters_by_status(): void
    {
        $batch = InvoiceBatch::factory()->create();

        Invoice::factory()
            ->forBatch($batch)
            ->withLines(1)
            ->createQuietly(['status' => InvoiceStatus::Open]);

        Invoice::factory()
            ->forBatch($batch)
            ->withLines(1)
            ->createQuietly(['status' => InvoiceStatus::Paid]);

        $batch->load('invoices.lines');

        static::assertGreaterThan(0.0, $batch->total->price);
        static::assertGreaterThan(0.0, $batch->openTotal->price);
        static::assertLessThan($batch->total->price, $batch->openTotal->price);
    }
}
