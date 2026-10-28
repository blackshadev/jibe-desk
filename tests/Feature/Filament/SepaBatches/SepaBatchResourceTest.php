<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\SepaBatches;

use App\Domain\Authorization\ResourcePermission;
use App\Domain\Billing\Invoices\InvoiceStatus;
use App\Domain\Billing\Payment\Readiness\ExportReadinessReason;
use App\Domain\Billing\Payment\Readiness\ExportReadinessService;
use App\Domain\Billing\Payment\Readiness\MandateReadiness;
use App\Domain\Billing\Payment\Readiness\MandateReadinessRepository;
use App\Domain\Billing\Payment\Readiness\MandateReadinessStatus;
use App\Domain\Billing\Payment\SepaBatchId;
use App\Domain\Billing\Payment\SepaBatchStatus;
use App\Domain\Billing\Payment\SequenceType;
use App\Domain\Billing\PurchaseOrders\PurchaseOrderStatus;
use App\Domain\Members\MemberId;
use App\Filament\Admin\Resources\SepaBatches\Pages\EditSepaBatch;
use App\Filament\Admin\Resources\SepaBatches\Pages\ListSepaBatches;
use App\Filament\Admin\Resources\SepaBatches\RelationManagers\SepaBatchExportOverridesRelationManager;
use App\Filament\Admin\Resources\SepaBatches\RelationManagers\SepaBatchInvoicesRelationManager;
use App\Filament\Admin\Resources\SepaBatches\RelationManagers\SepaBatchPurchaseOrdersRelationManager;
use App\Filament\Admin\Resources\SepaBatches\SepaBatchResource;
use App\Filament\Admin\Resources\SepaBatches\Widgets\BatchStatsOverview;
use App\Filament\Admin\Resources\SepaBatches\Widgets\SepaBatchGenerationProgress;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Member;
use App\Models\PaymentInformation;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\SepaBatch;
use App\Models\SepaExportOverride;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Resources\RelationManagers\RelationManagerConfiguration;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Override;
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

        Livewire::test(SepaBatchPurchaseOrdersRelationManager::class, [
            'ownerRecord' => $batch,
            'pageClass' => EditSepaBatch::class,
        ])
            ->assertTableActionVisible('addPurchaseOrders');
    }

    public function test_add_purchase_orders_action_is_hidden_on_pending_batch(): void
    {
        $this->withAuthorizedUser();

        $batch = SepaBatch::factory()->create(['status' => SepaBatchStatus::Pending]);

        Livewire::test(SepaBatchPurchaseOrdersRelationManager::class, [
            'ownerRecord' => $batch,
            'pageClass' => EditSepaBatch::class,
        ])
            ->assertTableActionHidden('addPurchaseOrders');
    }

    public function test_add_purchase_orders_action_attaches_purchase_orders_to_batch(): void
    {
        $this->withAuthorizedUser();

        $batch = SepaBatch::factory()->create(['status' => SepaBatchStatus::Open]);
        $purchaseOrder = PurchaseOrder::factory()->create([
            'status' => PurchaseOrderStatus::Pending,
            'sepa_batch_id' => null,
        ]);

        Livewire::test(SepaBatchPurchaseOrdersRelationManager::class, [
            'ownerRecord' => $batch,
            'pageClass' => EditSepaBatch::class,
        ])
            ->callTableAction('addPurchaseOrders', data: [
                'purchase_order_ids' => [$purchaseOrder->id],
            ]);

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $purchaseOrder->id,
            'sepa_batch_id' => $batch->id,
        ]);
    }

    public function test_add_invoices_action_is_visible_on_open_batch(): void
    {
        $this->withAuthorizedUser();

        $batch = SepaBatch::factory()->create(['status' => SepaBatchStatus::Open]);

        Livewire::test(SepaBatchInvoicesRelationManager::class, [
            'ownerRecord' => $batch,
            'pageClass' => EditSepaBatch::class,
        ])
            ->assertTableActionVisible('addInvoices');
    }

    public function test_add_invoices_action_attaches_invoices_to_batch(): void
    {
        $this->withAuthorizedUser();

        $batch = SepaBatch::factory()->create(['status' => SepaBatchStatus::Open]);
        $invoice = Invoice::factory()->createQuietly([
            'status' => InvoiceStatus::Open,
            'sepa_batch_id' => null,
        ]);

        Livewire::test(SepaBatchInvoicesRelationManager::class, [
            'ownerRecord' => $batch,
            'pageClass' => EditSepaBatch::class,
        ])
            ->callTableAction('addInvoices', data: [
                'invoice_ids' => [$invoice->id],
            ]);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'sepa_batch_id' => $batch->id,
        ]);
    }

    public function test_export_overrides_relation_manager_is_hidden_when_the_batch_has_no_overrides(): void
    {
        $this->withAuthorizedUser();

        $batch = SepaBatch::factory()->create(['status' => SepaBatchStatus::Pending]);

        $component = Livewire::test(EditSepaBatch::class, ['record' => $batch->getRouteKey()])
            ->assertSuccessful()
            ->instance();

        $managers = array_map(
            static fn (RelationManagerConfiguration $manager): string => $manager->relationManager,
            array_values($component->getRelationManagers()),
        );

        static::assertNotContains(SepaBatchExportOverridesRelationManager::class, $managers);
    }

    public function test_export_overrides_relation_manager_is_shown_when_the_batch_has_overrides(): void
    {
        $this->withAuthorizedUser();

        $batch = SepaBatch::factory()->create(['status' => SepaBatchStatus::Pending]);
        $invoice = Invoice::factory()
            ->forBatch($batch)
            ->createQuietly(['status' => InvoiceStatus::Pending]);

        SepaExportOverride::factory()->createQuietly([
            'sepa_batch_id' => $batch->id,
            'invoice_id' => $invoice->id,
        ]);

        $component = Livewire::test(EditSepaBatch::class, ['record' => $batch->getRouteKey()])
            ->assertSuccessful()
            ->instance();

        $managers = array_map(
            static fn (RelationManagerConfiguration $manager): string => $manager->relationManager,
            array_values($component->getRelationManagers()),
        );

        static::assertContains(SepaBatchExportOverridesRelationManager::class, $managers);
    }

    public function test_edit_page_stays_accessible_after_an_update_without_overrides(): void
    {
        $this->withAuthorizedUser();

        $batch = SepaBatch::factory()->create(['status' => SepaBatchStatus::Pending]);

        Livewire::test(EditSepaBatch::class, ['record' => $batch->getRouteKey()])
            ->call('refresh')
            ->assertSuccessful()
            ->assertDontSeeLivewire(SepaBatchExportOverridesRelationManager::class);
    }

    public function test_export_overrides_relation_manager_lists_the_override_details(): void
    {
        $this->withAuthorizedUser();

        $batch = SepaBatch::factory()->create(['status' => SepaBatchStatus::Pending]);
        $invoice = Invoice::factory()
            ->forBatch($batch)
            ->createQuietly(['status' => InvoiceStatus::Pending]);
        $user = User::factory()->createQuietly(['name' => 'Piet Exporteur']);

        $override = SepaExportOverride::factory()->createQuietly([
            'sepa_batch_id' => $batch->id,
            'invoice_id' => $invoice->id,
            'reason' => ExportReadinessReason::ExpiredMandate,
            'note' => 'Telefonisch akkoord van het lid.',
            'exported_by' => $user->id,
        ]);

        Livewire::test(SepaBatchExportOverridesRelationManager::class, [
            'ownerRecord' => $batch,
            'pageClass' => EditSepaBatch::class,
        ])
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$override])
            ->assertSee($invoice->invoice_number)
            ->assertSee(__('labels.export_readiness.reason.expired_mandate'))
            ->assertSee('Telefonisch akkoord van het lid.')
            ->assertSee('Piet Exporteur');
    }

    public function test_export_overrides_relation_manager_only_lists_overrides_of_the_batch(): void
    {
        $this->withAuthorizedUser();

        $batch = SepaBatch::factory()->create(['status' => SepaBatchStatus::Pending]);
        $otherBatch = SepaBatch::factory()->create(['status' => SepaBatchStatus::Pending]);

        $ownOverride = SepaExportOverride::factory()->createQuietly(['sepa_batch_id' => $batch->id]);
        $otherOverride = SepaExportOverride::factory()->createQuietly(['sepa_batch_id' => $otherBatch->id]);

        Livewire::test(SepaBatchExportOverridesRelationManager::class, [
            'ownerRecord' => $batch,
            'pageClass' => EditSepaBatch::class,
        ])
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$ownOverride])
            ->assertCanNotSeeTableRecords([$otherOverride]);
    }

    public function test_retry_declined_action_moves_declined_invoices_into_the_chosen_open_batch(): void
    {
        $this->withAuthorizedUser();

        $sourceBatch = SepaBatch::factory()->create(['status' => SepaBatchStatus::Pending]);
        $targetBatch = SepaBatch::factory()->create(['status' => SepaBatchStatus::Open]);
        $invoice = Invoice::factory()->createQuietly([
            'status' => InvoiceStatus::Declined,
            'sepa_batch_id' => $sourceBatch->id,
            'sent_at' => now(),
        ]);

        Livewire::test(EditSepaBatch::class, ['record' => $sourceBatch->getRouteKey()])
            ->callAction('retryDeclined', data: ['target_batch_id' => $targetBatch->id]);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => InvoiceStatus::Open->value,
            'sepa_batch_id' => $targetBatch->id,
            'sent_at' => null,
        ]);
    }

    public function test_retry_declined_action_is_hidden_when_the_batch_has_no_declined_invoices(): void
    {
        $this->withAuthorizedUser();

        $batch = SepaBatch::factory()->create(['status' => SepaBatchStatus::Pending]);
        Invoice::factory()->createQuietly([
            'status' => InvoiceStatus::Open,
            'sepa_batch_id' => $batch->id,
        ]);

        Livewire::test(EditSepaBatch::class, ['record' => $batch->getRouteKey()])
            ->assertActionHidden('retryDeclined');
    }

    public function test_retry_declined_action_rejects_a_non_open_target_batch(): void
    {
        $this->withAuthorizedUser();

        $sourceBatch = SepaBatch::factory()->create(['status' => SepaBatchStatus::Pending]);
        $targetBatch = SepaBatch::factory()->create(['status' => SepaBatchStatus::Pending]);
        SepaBatch::factory()->create(['status' => SepaBatchStatus::Open]);
        $invoice = Invoice::factory()->createQuietly([
            'status' => InvoiceStatus::Declined,
            'sepa_batch_id' => $sourceBatch->id,
        ]);

        Livewire::test(EditSepaBatch::class, ['record' => $sourceBatch->getRouteKey()])
            ->callAction('retryDeclined', data: ['target_batch_id' => $targetBatch->id])
            ->assertHasActionErrors(['target_batch_id']);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => InvoiceStatus::Declined->value,
            'sepa_batch_id' => $sourceBatch->id,
        ]);
    }

    public function test_readiness_action_lists_expected_manual_payer_exclusions(): void
    {
        $this->withAuthorizedUser();

        $batch = SepaBatch::factory()->create(['status' => SepaBatchStatus::Pending]);
        $member = Member::factory()->transfer()->createQuietly();
        $invoice = Invoice::factory()
            ->forMember($member)
            ->forBatch($batch)
            ->createQuietly(['status' => InvoiceStatus::Pending]);
        $this->createInvoiceLine($invoice);

        Livewire::test(EditSepaBatch::class, ['record' => $batch->getRouteKey()])
            ->mountAction('readiness')
            ->assertMountedActionModalSee(__('labels.export_readiness.expected_exclusions'));
    }

    public function test_readiness_action_lists_broken_data(): void
    {
        $this->withAuthorizedUser();

        $batch = SepaBatch::factory()->create(['status' => SepaBatchStatus::Pending]);
        Invoice::factory()
            ->forBatch($batch)
            ->createQuietly(['status' => InvoiceStatus::Pending]);

        Livewire::test(EditSepaBatch::class, ['record' => $batch->getRouteKey()])
            ->mountAction('readiness')
            ->assertMountedActionModalSee(__('labels.export_readiness.broken_data'));
    }

    public function test_readiness_action_lists_refund_exclusions(): void
    {
        $this->withAuthorizedUser();

        $batch = SepaBatch::factory()->create(['status' => SepaBatchStatus::Pending]);
        $member = Member::factory()->createQuietly();
        $invoice = Invoice::factory()
            ->forMember($member)
            ->forBatch($batch)
            ->createQuietly(['status' => InvoiceStatus::Pending]);
        InvoiceLine::factory()
            ->state(['invoice_id' => $invoice->id])
            ->createQuietly(['price' => -10.00, 'quantity' => 1, 'vat' => -2.10]);

        Livewire::test(EditSepaBatch::class, ['record' => $batch->getRouteKey()])
            ->mountAction('readiness')
            ->assertMountedActionModalSee(__('labels.export_readiness.refund_exclusions'));
    }

    public function test_close_batch_warns_but_still_closes_the_batch(): void
    {
        $this->withAuthorizedUser();

        $batch = SepaBatch::factory()->create(['status' => SepaBatchStatus::Open]);

        $component = Livewire::test(EditSepaBatch::class, ['record' => $batch->getRouteKey()])
            ->mountAction('closeBatch')
            ->assertMountedActionModalSee(__('labels.export_readiness.close_warning'));

        $component->callMountedAction();

        $batch->refresh();

        static::assertSame(SepaBatchStatus::Pending, $batch->status);
        static::assertNotNull($batch->closed_at);
    }

    public function test_transfer_date_is_editable_on_a_pending_unexported_batch(): void
    {
        $this->withAuthorizedUser();

        $batch = SepaBatch::factory()->create([
            'status' => SepaBatchStatus::Pending,
            'exported_at' => null,
        ]);

        Livewire::test(EditSepaBatch::class, ['record' => $batch->getRouteKey()])
            ->assertFormFieldEnabled('sepa_transfer_date');
    }

    public function test_transfer_date_is_read_only_after_the_first_export(): void
    {
        $this->withAuthorizedUser();

        $batch = SepaBatch::factory()->create([
            'status' => SepaBatchStatus::Pending,
            'exported_at' => CarbonImmutable::parse('2026-10-22 12:00:00'),
        ]);

        Livewire::test(EditSepaBatch::class, ['record' => $batch->getRouteKey()])
            ->assertFormFieldDisabled('sepa_transfer_date');
    }

    public function test_invoice_date_is_read_only_after_close(): void
    {
        $this->withAuthorizedUser();

        $batch = SepaBatch::factory()->create(['status' => SepaBatchStatus::Pending]);

        Livewire::test(EditSepaBatch::class, ['record' => $batch->getRouteKey()])
            ->assertFormFieldDisabled('invoice_date');
    }

    public function test_editing_the_transfer_date_changes_the_next_readiness_assessment(): void
    {
        $this->withAuthorizedUser();

        $batch = SepaBatch::factory()->create([
            'status' => SepaBatchStatus::Pending,
            'invoice_date' => '2026-09-25',
            'sepa_transfer_date' => '2026-10-10',
            'closed_at' => '2026-10-01 12:00:00',
        ]);

        Livewire::test(EditSepaBatch::class, ['record' => $batch->getRouteKey()])
            ->mountAction('readiness')
            ->assertMountedActionModalSee(__('labels.export_readiness.batch_level'));

        Livewire::test(EditSepaBatch::class, ['record' => $batch->getRouteKey()])
            ->fillForm(['sepa_transfer_date' => '20-10-2026'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('sepa_batches', [
            'id' => $batch->id,
            'sepa_transfer_date' => '2026-10-20 00:00:00',
        ]);

        $report = $this->app->make(ExportReadinessService::class)->assess(SepaBatchId::create($batch->id));

        static::assertSame([], $report->batchLevelIssues());
    }

    public function test_export_action_is_hidden_without_update_permission(): void
    {
        $this->withAuthorizedUser();

        Gate::before(static fn (User $_user, string $ability): ?bool => $ability === 'export' ? false : null);

        $batch = $this->exportableBatch();

        Livewire::test(EditSepaBatch::class, ['record' => $batch->getRouteKey()])
            ->assertActionHidden('exportSepa');
    }

    public function test_export_action_is_blocked_by_broken_data(): void
    {
        $this->withAuthorizedUser();

        $batch = $this->exportableBatch();
        Invoice::factory()
            ->forBatch($batch)
            ->createQuietly(['status' => InvoiceStatus::Pending]);

        Livewire::test(EditSepaBatch::class, ['record' => $batch->getRouteKey()])
            ->callAction('exportSepa')
            ->assertNotified(__('notifications.sepa_export_blocked'));

        static::assertNull($batch->fresh()->exported_at);
    }

    public function test_export_action_requires_exclusion_acknowledgement(): void
    {
        $this->withAuthorizedUser();

        $batch = $this->exportableBatch();
        $member = Member::factory()->transfer()->createQuietly();
        $invoice = Invoice::factory()
            ->forMember($member)
            ->forBatch($batch)
            ->createQuietly(['status' => InvoiceStatus::Pending]);
        $this->createInvoiceLine($invoice);

        Livewire::test(EditSepaBatch::class, ['record' => $batch->getRouteKey()])
            ->callAction('exportSepa')
            ->assertHasActionErrors(['acknowledge_exclusions']);

        static::assertNull($batch->fresh()->exported_at);
    }

    public function test_export_action_requires_a_reason_for_an_expired_mandate_override(): void
    {
        $this->withAuthorizedUser();
        $this->bindExpiredMandateReadiness();

        $batch = $this->exportableBatch();
        $invoice = $this->createInvoiceForBatch($batch);

        Livewire::test(EditSepaBatch::class, ['record' => $batch->getRouteKey()])
            ->callAction('exportSepa', data: [
                'acknowledge_exclusions' => true,
                'overridden_invoices' => [$invoice->id],
                'override_reason' => '',
            ])
            ->assertHasActionErrors(['override_reason']);

        static::assertNull($batch->fresh()->exported_at);
    }

    public function test_export_action_requires_the_override_again_on_a_second_export(): void
    {
        $this->withAuthorizedUser();
        $this->bindExpiredMandateReadiness();

        $batch = $this->exportableBatch();
        $invoice = $this->createInvoiceForBatch($batch);
        $this->attachPaymentInformation($invoice);

        Livewire::test(EditSepaBatch::class, ['record' => $batch->getRouteKey()])
            ->callAction('exportSepa', data: [
                'acknowledge_exclusions' => true,
                'overridden_invoices' => [$invoice->id],
                'override_reason' => 'Written confirmation received.',
            ])
            ->assertFileDownloaded();

        Livewire::test(EditSepaBatch::class, ['record' => $batch->getRouteKey()])
            ->callAction('exportSepa', data: ['acknowledge_exclusions' => true])
            ->assertNotified(__('notifications.sepa_export_blocked'));

        $this->assertDatabaseCount('sepa_export_overrides', 1);
    }

    public function test_export_action_requires_exclusion_acknowledgement_on_every_export(): void
    {
        $this->withAuthorizedUser();

        $batch = $this->exportableBatch();
        $member = Member::factory()->transfer()->createQuietly();
        $invoice = Invoice::factory()
            ->forMember($member)
            ->forBatch($batch)
            ->createQuietly(['status' => InvoiceStatus::Pending]);
        $this->createInvoiceLine($invoice);
        $this->attachPurchaseOrder($batch);

        Livewire::test(EditSepaBatch::class, ['record' => $batch->getRouteKey()])
            ->callAction('exportSepa', data: ['acknowledge_exclusions' => true])
            ->assertFileDownloaded();

        Livewire::test(EditSepaBatch::class, ['record' => $batch->getRouteKey()])
            ->callAction('exportSepa')
            ->assertHasActionErrors(['acknowledge_exclusions']);
    }

    public function test_export_action_records_the_override_and_stamps_the_export(): void
    {
        $this->withAuthorizedUser();
        $this->bindExpiredMandateReadiness();

        $batch = $this->exportableBatch();
        $invoice = $this->createInvoiceForBatch($batch);
        $this->attachPaymentInformation($invoice);

        Livewire::test(EditSepaBatch::class, ['record' => $batch->getRouteKey()])
            ->callAction('exportSepa', data: [
                'acknowledge_exclusions' => true,
                'overridden_invoices' => [$invoice->id],
                'override_reason' => 'Written confirmation received.',
            ])
            ->assertFileDownloaded();

        static::assertNotNull($batch->fresh()->exported_at);
        $this->assertDatabaseHas('sepa_export_overrides', [
            'sepa_batch_id' => $batch->id,
            'invoice_id' => $invoice->id,
            'reason' => 'expired_mandate',
            'note' => 'Written confirmation received.',
        ]);
    }

    public function test_export_action_re_stamps_exported_at_on_a_second_export(): void
    {
        $this->withAuthorizedUser();

        $batch = $this->exportableBatch();
        $invoice = $this->createInvoiceForBatch($batch);
        PaymentInformation::factory()->createQuietly([
            'member_id' => $invoice->member_id,
            'banking_account_number' => 'NL91ABNA0417164300',
            'banking_bic' => 'ABNANL2A',
        ]);

        $this->travelTo(CarbonImmutable::parse('2026-10-22 10:00:00'));

        Livewire::test(EditSepaBatch::class, ['record' => $batch->getRouteKey()])
            ->callAction('exportSepa')
            ->assertFileDownloaded();

        static::assertSame('2026-10-22 10:00:00', $batch->fresh()->exported_at?->format('Y-m-d H:i:s'));

        $this->travelTo(CarbonImmutable::parse('2026-10-23 10:00:00'));

        Livewire::test(EditSepaBatch::class, ['record' => $batch->getRouteKey()])
            ->callAction('exportSepa')
            ->assertFileDownloaded();

        static::assertSame('2026-10-23 10:00:00', $batch->fresh()->exported_at?->format('Y-m-d H:i:s'));
    }

    public function test_mark_submitted_action_is_hidden_before_export(): void
    {
        $this->withAuthorizedUser();

        $batch = SepaBatch::factory()->create([
            'status' => SepaBatchStatus::Pending,
            'exported_at' => null,
        ]);

        Livewire::test(EditSepaBatch::class, ['record' => $batch->getRouteKey()])
            ->assertActionHidden('markSubmitted');
    }

    public function test_mark_submitted_action_stamps_the_user_and_time(): void
    {
        $user = $this->withAuthorizedUser();

        $batch = SepaBatch::factory()->create([
            'status' => SepaBatchStatus::Pending,
            'exported_at' => CarbonImmutable::parse('2026-10-22 12:00:00'),
        ]);

        Livewire::test(EditSepaBatch::class, ['record' => $batch->getRouteKey()])
            ->callAction('markSubmitted');

        $batch->refresh();

        static::assertNotNull($batch->submitted_at);
        static::assertSame($user->id, $batch->submitted_by);
    }

    public function test_mark_submitted_action_is_hidden_after_submission(): void
    {
        $this->withAuthorizedUser();

        $batch = SepaBatch::factory()->create([
            'status' => SepaBatchStatus::Pending,
            'exported_at' => CarbonImmutable::parse('2026-10-22 12:00:00'),
            'submitted_at' => CarbonImmutable::parse('2026-10-23 12:00:00'),
        ]);

        Livewire::test(EditSepaBatch::class, ['record' => $batch->getRouteKey()])
            ->assertActionHidden('markSubmitted');
    }

    public function test_mark_submitted_action_is_hidden_without_update_permission(): void
    {
        $this->withAuthorizedUser();

        Gate::before(static fn (User $_user, string $ability): ?bool => $ability === 'markSubmitted' ? false : null);

        $batch = SepaBatch::factory()->create([
            'status' => SepaBatchStatus::Pending,
            'exported_at' => CarbonImmutable::parse('2026-10-22 12:00:00'),
        ]);

        Livewire::test(EditSepaBatch::class, ['record' => $batch->getRouteKey()])
            ->assertActionHidden('markSubmitted');
    }

    public function test_export_action_refreshes_the_page_state(): void
    {
        $this->withAuthorizedUser();

        $batch = $this->exportableBatch();
        $invoice = $this->createInvoiceForBatch($batch);
        $this->attachPaymentInformation($invoice);

        $component = Livewire::test(EditSepaBatch::class, ['record' => $batch->getRouteKey()])
            ->callAction('exportSepa')
            ->assertFileDownloaded();

        $component->assertDispatched('refresh');

        $component
            ->dispatch('refresh')
            ->assertActionVisible('markSubmitted');
    }

    public function test_batch_stats_overview_refreshes_when_the_batch_is_updated(): void
    {
        $this->withAuthorizedUser();

        $batch = SepaBatch::factory()->create(['exported_at' => null]);

        $component = Livewire::test(BatchStatsOverview::class, ['record' => $batch]);

        SepaBatch::query()
            ->whereKey($batch->id)
            ->update(['exported_at' => '2026-10-22 12:00:00']);

        $component
            ->dispatch('refresh')
            ->assertSee('22-10-2026 12:00');
    }

    public function test_replan_sequence_action_is_visible_on_a_pending_unexported_batch(): void
    {
        $this->withAuthorizedUser();

        $batch = SepaBatch::factory()->create([
            'status' => SepaBatchStatus::Pending,
            'exported_at' => null,
            'submitted_at' => null,
        ]);

        Livewire::test(EditSepaBatch::class, ['record' => $batch->getRouteKey()])
            ->assertActionVisible('replanSequence');
    }

    public function test_replan_sequence_action_is_hidden_after_the_first_export(): void
    {
        $this->withAuthorizedUser();

        $batch = SepaBatch::factory()->create([
            'status' => SepaBatchStatus::Pending,
            'exported_at' => CarbonImmutable::parse('2026-10-22 12:00:00'),
        ]);

        Livewire::test(EditSepaBatch::class, ['record' => $batch->getRouteKey()])
            ->assertActionHidden('replanSequence');
    }

    public function test_replan_sequence_action_is_hidden_after_submission(): void
    {
        $this->withAuthorizedUser();

        $batch = SepaBatch::factory()->create([
            'status' => SepaBatchStatus::Pending,
            'exported_at' => CarbonImmutable::parse('2026-10-22 12:00:00'),
            'submitted_at' => CarbonImmutable::parse('2026-10-23 12:00:00'),
        ]);

        Livewire::test(EditSepaBatch::class, ['record' => $batch->getRouteKey()])
            ->assertActionHidden('replanSequence');
    }

    public function test_replan_sequence_action_is_hidden_without_update_permission(): void
    {
        $this->withAuthorizedUser();

        Gate::before(static fn (User $_user, string $ability): ?bool => $ability === 'replanSequence' ? false : null);

        $batch = SepaBatch::factory()->create([
            'status' => SepaBatchStatus::Pending,
            'exported_at' => null,
        ]);

        Livewire::test(EditSepaBatch::class, ['record' => $batch->getRouteKey()])
            ->assertActionHidden('replanSequence');
    }

    public function test_replan_sequence_action_clears_and_refreezes_the_plan(): void
    {
        $this->withAuthorizedUser();

        $batch = SepaBatch::factory()->create([
            'status' => SepaBatchStatus::Pending,
            'exported_at' => null,
        ]);
        $invoice = $this->createInvoiceForBatch($batch);
        Invoice::query()->whereKey($invoice->id)->update(['sepa_sequence_type' => SequenceType::First]);

        PaymentInformation::factory()
            ->collectedAt(CarbonImmutable::parse('2026-09-30 00:00:00'))
            ->createQuietly(['member_id' => $invoice->member_id]);

        Livewire::test(EditSepaBatch::class, ['record' => $batch->getRouteKey()])
            ->callAction('replanSequence');

        static::assertSame(SequenceType::Recurring, $invoice->refresh()->sepa_sequence_type);
    }

    public function test_replan_sequence_action_unblocks_a_stale_export(): void
    {
        $this->withAuthorizedUser();

        $batch = $this->exportableBatch();
        $invoice = $this->createInvoiceForBatch($batch);
        Invoice::query()->whereKey($invoice->id)->update(['sepa_sequence_type' => SequenceType::First]);

        PaymentInformation::factory()
            ->collectedAt(CarbonImmutable::parse('2026-09-30 00:00:00'))
            ->createQuietly([
                'member_id' => $invoice->member_id,
                'banking_account_number' => 'NL91ABNA0417164300',
                'banking_bic' => 'ABNANL2A',
            ]);

        Livewire::test(EditSepaBatch::class, ['record' => $batch->getRouteKey()])
            ->callAction('replanSequence');

        Livewire::test(EditSepaBatch::class, ['record' => $batch->getRouteKey()])
            ->callAction('exportSepa', data: ['acknowledge_exclusions' => true])
            ->assertFileDownloaded();
    }

    private function exportableBatch(): SepaBatch
    {
        return SepaBatch::factory()->create([
            'status' => SepaBatchStatus::Pending,
            'invoice_date' => '2026-09-25',
            'sepa_transfer_date' => '2026-10-20',
            'closed_at' => '2026-10-01 12:00:00',
        ]);
    }

    private function createInvoiceForBatch(SepaBatch $batch): Invoice
    {
        $member = Member::factory()->createQuietly();
        $invoice = Invoice::factory()
            ->forMember($member)
            ->forBatch($batch)
            ->createQuietly(['status' => InvoiceStatus::Pending]);

        $this->createInvoiceLine($invoice);

        return $invoice;
    }

    private function createInvoiceLine(Invoice $invoice): void
    {
        InvoiceLine::factory()
            ->state(['invoice_id' => $invoice->id])
            ->createQuietly(['price' => 10.00, 'quantity' => 1, 'vat' => 2.10]);
    }

    private function attachPaymentInformation(Invoice $invoice): void
    {
        PaymentInformation::factory()->createQuietly([
            'member_id' => $invoice->member_id,
            'banking_account_number' => 'NL91ABNA0417164300',
            'banking_bic' => 'ABNANL2A',
        ]);
    }

    private function attachPurchaseOrder(SepaBatch $batch): void
    {
        $purchaseOrder = PurchaseOrder::factory()
            ->accepted()
            ->createQuietly([
                'sepa_batch_id' => $batch->id,
                'status' => PurchaseOrderStatus::Pending,
                'creditor_name' => 'Supplier Ltd',
                'creditor_iban' => 'NL20RABO0123456789',
            ]);

        PurchaseOrderLine::factory()->createQuietly([
            'purchase_order_id' => $purchaseOrder->id,
            'price' => 50.00,
            'price_vat' => 10.50,
        ]);
    }

    private function bindExpiredMandateReadiness(): void
    {
        $this->app->instance(MandateReadinessRepository::class, new class() implements MandateReadinessRepository {
            /** @param list<MemberId> $memberIds */
            #[Override]
            public function forMembers(array $memberIds): array
            {
                $mandates = [];

                foreach ($memberIds as $memberId) {
                    $mandates[$memberId->value] = new MandateReadiness(
                        status: MandateReadinessStatus::Expired,
                        iban: 'NL91ABNA0417164300',
                        bic: 'ABNANL2A',
                        expectedSequenceType: SequenceType::First,
                    );
                }

                return $mandates;
            }
        });
    }
}
