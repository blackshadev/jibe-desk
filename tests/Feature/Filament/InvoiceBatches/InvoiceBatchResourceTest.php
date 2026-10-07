<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\InvoiceBatches;

use App\Domain\Authorization\ResourcePermission;
use App\Domain\Invoices\InvoiceBatchStatus;
use App\Domain\Invoices\InvoiceStatus;
use App\Filament\Admin\Resources\InvoiceBatches\InvoiceBatchResource;
use App\Filament\Admin\Resources\InvoiceBatches\Pages\ListInvoiceBatches;
use App\Filament\Admin\Resources\InvoiceBatches\Widgets\BatchStatsOverview;
use App\Filament\Admin\Resources\InvoiceBatches\Widgets\InvoiceBatchGenerationProgress;
use App\Models\Invoice;
use App\Models\InvoiceBatch;
use Livewire\Livewire;
use Tests\Concerns\WithAuthorizedUser;
use Tests\FeatureTestCase;

final class InvoiceBatchResourceTest extends FeatureTestCase
{
    use WithAuthorizedUser;

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

    public function test_list_page_shows_generate_invoice_batch_action(): void
    {
        $this->withAuthorizedUser();

        Livewire::test(ListInvoiceBatches::class)
            ->assertActionVisible('generate_invoice_batch');
    }

    public function test_generate_invoice_batch_action_creates_batch_and_redirects_to_it(): void
    {
        $this->withAuthorizedUser();

        Livewire::test(ListInvoiceBatches::class)
            ->callAction('generate_invoice_batch', data: [
                'invoice_date' => '25-05-2026',
                'sepa_transfer_date' => '18-06-2026',
            ])
            ->assertRedirect(
                InvoiceBatchResource::getUrl('edit', ['record' => 1]),
            );

        $this->assertDatabaseHas('invoice_batches', [
            'id' => 1,
            'invoice_date' => '2026-05-25 00:00:00',
            'sepa_transfer_date' => '2026-06-18 00:00:00',
            'status' => InvoiceBatchStatus::Open->value,
        ]);
    }

    public function test_generate_invoice_batch_action_is_hidden_without_create_permission(): void
    {
        $user = $this->withAuthorizedUser();
        $user->syncRoles([]);
        $user->givePermissionTo(ResourcePermission::ViewAnyInvoiceBatches->value);

        Livewire::test(ListInvoiceBatches::class)
            ->assertActionHidden('generate_invoice_batch');
    }

    public function test_batch_reports_generating_while_started_but_not_finished(): void
    {
        $batch = InvoiceBatch::factory()->generating()->create();

        static::assertTrue($batch->isGenerating());
    }

    public function test_batch_is_not_generating_when_finished(): void
    {
        $batch = InvoiceBatch::factory()->generationFinished()->create();

        static::assertFalse($batch->isGenerating());
    }

    public function test_batch_is_not_generating_when_generation_never_started(): void
    {
        $batch = InvoiceBatch::factory()->create();

        static::assertFalse($batch->isGenerating());
    }

    public function test_edit_page_shows_progress_widget_while_generating(): void
    {
        $this->withAuthorizedUser();

        $batch = InvoiceBatch::factory()->generating(12)->create();

        $this
            ->get(InvoiceBatchResource::getUrl('edit', ['record' => $batch]))
            ->assertOk()
            ->assertSeeLivewire(InvoiceBatchGenerationProgress::class);
    }

    public function test_edit_page_shows_stats_widget_when_generation_finished(): void
    {
        $this->withAuthorizedUser();

        $batch = InvoiceBatch::factory()->generationFinished()->create();

        $this
            ->get(InvoiceBatchResource::getUrl('edit', ['record' => $batch]))
            ->assertOk()
            ->assertSeeLivewire(BatchStatsOverview::class)
            ->assertDontSeeLivewire(InvoiceBatchGenerationProgress::class);
    }
}
