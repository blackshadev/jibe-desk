<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\SepaBatches;

use App\Domain\Authorization\ResourcePermission;
use App\Domain\Billing\Invoices\InvoiceStatus;
use App\Domain\Billing\Payment\SepaBatchStatus;
use App\Domain\Billing\PurchaseOrders\PurchaseOrderStatus;
use App\Filament\Admin\Resources\SepaBatches\Pages\EditSepaBatch;
use App\Filament\Admin\Resources\SepaBatches\Pages\ListSepaBatches;
use App\Filament\Admin\Resources\SepaBatches\SepaBatchResource;
use App\Filament\Admin\Resources\SepaBatches\Widgets\BatchStatsOverview;
use App\Filament\Admin\Resources\SepaBatches\Widgets\SepaBatchGenerationProgress;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\SepaBatch;
use Livewire\Livewire;
use Tests\Concerns\WithAuthorizedUser;
use Tests\FeatureTestCase;

final class SepaBatchResourceTest extends FeatureTestCase
{
    use WithAuthorizedUser;

    public function test_batch_can_be_created(): void
    {
        $batch = SepaBatch::factory()->create();

        $this->assertDatabaseHas('sepa_batches', [
            'id' => $batch->id,
            'status' => SepaBatchStatus::Open->value,
        ]);
    }

    public function test_batch_can_be_created_with_open_status(): void
    {
        $batch = SepaBatch::factory()->create(['status' => SepaBatchStatus::Open]);

        static::assertSame(SepaBatchStatus::Open, $batch->status);
    }

    public function test_batch_can_be_marked_as_pending(): void
    {
        $batch = SepaBatch::factory()->create(['status' => SepaBatchStatus::Open]);
        $batch->update(['status' => SepaBatchStatus::Pending]);

        $this->assertDatabaseHas('sepa_batches', [
            'id' => $batch->id,
            'status' => SepaBatchStatus::Pending->value,
        ]);
    }

    public function test_batch_can_be_completed(): void
    {
        $batch = SepaBatch::factory()->create(['status' => SepaBatchStatus::Pending]);
        $batch->update(['status' => SepaBatchStatus::Completed]);

        $this->assertDatabaseHas('sepa_batches', [
            'id' => $batch->id,
            'status' => SepaBatchStatus::Completed->value,
        ]);
    }

    public function test_invoices_can_be_attached_to_batch(): void
    {
        $batch = SepaBatch::factory()->create();
        $invoice = Invoice::factory()->createQuietly([
            'status' => InvoiceStatus::Open,
            'sepa_batch_id' => null,
        ]);

        $invoice->update(['sepa_batch_id' => $batch->id]);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'sepa_batch_id' => $batch->id,
        ]);
    }

    public function test_invoices_can_be_detached_from_batch(): void
    {
        $batch = SepaBatch::factory()->create();
        $invoice = Invoice::factory()
            ->forBatch($batch)
            ->createQuietly(['status' => InvoiceStatus::Open]);

        $invoice->update(['sepa_batch_id' => null]);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'sepa_batch_id' => null,
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
        $batch = SepaBatch::factory()->create();

        $invoice = Invoice::factory()
            ->forBatch($batch)
            ->withLines(1)
            ->createQuietly();

        $batch->load('invoices.lines');

        static::assertGreaterThan(0.0, $batch->total->price);
    }

    public function test_open_total_filters_by_status(): void
    {
        $batch = SepaBatch::factory()->create();

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

    public function test_list_page_shows_generate_sepa_batch_action(): void
    {
        $this->withAuthorizedUser();

        Livewire::test(ListSepaBatches::class)
            ->assertActionVisible('generate_sepa_batch');
    }

    public function test_generate_sepa_batch_action_creates_batch_and_redirects_to_it(): void
    {
        $this->withAuthorizedUser();

        Livewire::test(ListSepaBatches::class)
            ->callAction('generate_sepa_batch', data: [
                'invoice_date' => '25-05-2026',
                'sepa_transfer_date' => '18-06-2026',
            ])
            ->assertRedirect(
                SepaBatchResource::getUrl('edit', ['record' => 1]),
            );

        $this->assertDatabaseHas('sepa_batches', [
            'id' => 1,
            'invoice_date' => '2026-05-25 00:00:00',
            'sepa_transfer_date' => '2026-06-18 00:00:00',
            'status' => SepaBatchStatus::Open->value,
        ]);
    }

    public function test_generate_sepa_batch_action_is_hidden_without_create_permission(): void
    {
        $user = $this->withAuthorizedUser();
        $user->syncRoles([]);
        $user->givePermissionTo(ResourcePermission::ViewAnySepaBatches->value);

        Livewire::test(ListSepaBatches::class)
            ->assertActionHidden('generate_sepa_batch');
    }

    public function test_batch_reports_generating_while_started_but_not_finished(): void
    {
        $batch = SepaBatch::factory()->generating()->create();

        static::assertTrue($batch->isGenerating());
    }

    public function test_batch_is_not_generating_when_finished(): void
    {
        $batch = SepaBatch::factory()->generationFinished()->create();

        static::assertFalse($batch->isGenerating());
    }

    public function test_batch_is_not_generating_when_generation_never_started(): void
    {
        $batch = SepaBatch::factory()->create();

        static::assertFalse($batch->isGenerating());
    }

    public function test_edit_page_shows_progress_widget_while_generating(): void
    {
        $this->withAuthorizedUser();

        $batch = SepaBatch::factory()->generating(12)->create();

        $this
            ->get(SepaBatchResource::getUrl('edit', ['record' => $batch]))
            ->assertOk()
            ->assertSeeLivewire(SepaBatchGenerationProgress::class);
    }

    public function test_edit_page_shows_stats_widget_when_generation_finished(): void
    {
        $this->withAuthorizedUser();

        $batch = SepaBatch::factory()->generationFinished()->create();

        $this
            ->get(SepaBatchResource::getUrl('edit', ['record' => $batch]))
            ->assertOk()
            ->assertSeeLivewire(BatchStatsOverview::class)
            ->assertDontSeeLivewire(SepaBatchGenerationProgress::class);
    }

    public function test_add_purchase_orders_action_is_visible_on_open_batch(): void
    {
        $this->withAuthorizedUser();

        $batch = SepaBatch::factory()->create(['status' => SepaBatchStatus::Open]);

        Livewire::test(EditSepaBatch::class, ['record' => $batch->getRouteKey()])
            ->assertActionVisible('addPurchaseOrders');
    }

    public function test_add_purchase_orders_action_is_hidden_on_pending_batch(): void
    {
        $this->withAuthorizedUser();

        $batch = SepaBatch::factory()->create(['status' => SepaBatchStatus::Pending]);

        Livewire::test(EditSepaBatch::class, ['record' => $batch->getRouteKey()])
            ->assertActionHidden('addPurchaseOrders');
    }

    public function test_add_purchase_orders_action_attaches_purchase_orders_to_batch(): void
    {
        $this->withAuthorizedUser();

        $batch = SepaBatch::factory()->create(['status' => SepaBatchStatus::Open]);
        $purchaseOrder = PurchaseOrder::factory()->create([
            'status' => PurchaseOrderStatus::Pending,
            'sepa_batch_id' => null,
        ]);

        Livewire::test(EditSepaBatch::class, ['record' => $batch->getRouteKey()])
            ->callAction('addPurchaseOrders', data: [
                'purchase_order_ids' => [$purchaseOrder->id],
            ]);

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $purchaseOrder->id,
            'sepa_batch_id' => $batch->id,
        ]);
    }
}
