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
    public function testBatchCanBeCreated(): void
    {
        $batch = InvoiceBatch::factory()->create();

        $this->assertDatabaseHas('invoice_batches', [
            'id' => $batch->id,
            'status' => InvoiceBatchStatus::Open->value,
        ]);
    }

    public function testBatchCanBeCreatedWithOpenStatus(): void
    {
        $batch = InvoiceBatch::factory()->create(['status' => InvoiceBatchStatus::Open]);

        static::assertSame(InvoiceBatchStatus::Open, $batch->status);
    }

    public function testBatchCanBeMarkedAsPending(): void
    {
        $batch = InvoiceBatch::factory()->create(['status' => InvoiceBatchStatus::Open]);
        $batch->update(['status' => InvoiceBatchStatus::Pending]);

        $this->assertDatabaseHas('invoice_batches', [
            'id' => $batch->id,
            'status' => InvoiceBatchStatus::Pending->value,
        ]);
    }

    public function testBatchCanBeCompleted(): void
    {
        $batch = InvoiceBatch::factory()->create(['status' => InvoiceBatchStatus::Pending]);
        $batch->update(['status' => InvoiceBatchStatus::Completed]);

        $this->assertDatabaseHas('invoice_batches', [
            'id' => $batch->id,
            'status' => InvoiceBatchStatus::Completed->value,
        ]);
    }

    public function testInvoicesCanBeAttachedToBatch(): void
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

    public function testInvoicesCanBeDetachedFromBatch(): void
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

    public function testInvoiceStatusCanBeUpdatedToPaid(): void
    {
        $invoice = Invoice::factory()->createQuietly(['status' => InvoiceStatus::Open]);

        $invoice->update(['status' => InvoiceStatus::Paid]);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => InvoiceStatus::Paid->value,
        ]);
    }

    public function testInvoiceStatusCanBeUpdatedToDeclined(): void
    {
        $invoice = Invoice::factory()->createQuietly(['status' => InvoiceStatus::Open]);

        $invoice->update(['status' => InvoiceStatus::Declined]);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => InvoiceStatus::Declined->value,
        ]);
    }

    public function testBatchTotalCanBeCalculated(): void
    {
        $batch = InvoiceBatch::factory()->create();

        $invoice = Invoice::factory()
            ->forBatch($batch)
            ->withLines(1)
            ->createQuietly();

        $batch->load('invoices.lines');

        static::assertGreaterThan(0.0, $batch->total->price);
    }

    public function testOpenTotalFiltersByStatus(): void
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
