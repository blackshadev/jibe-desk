<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Admin\Resources;

use App\Domain\Authorization\RoleName;
use App\Domain\PurchaseOrders\PurchaseOrderId;
use App\Domain\PurchaseOrders\PurchaseOrderIdList;
use App\Domain\PurchaseOrders\PurchaseOrderService;
use App\Domain\PurchaseOrders\PurchaseOrderStatus;
use App\Filament\Admin\Resources\PurchaseOrders\Pages\CreatePurchaseOrder;
use App\Filament\Admin\Resources\PurchaseOrders\Pages\EditPurchaseOrder;
use App\Filament\Admin\Resources\PurchaseOrders\Pages\ListPurchaseOrders;
use App\Models\CostCenter;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\Concerns\WithAuthorizedUser;
use Tests\FeatureTestCase;

final class PurchaseOrderResourceTest extends FeatureTestCase
{
    use WithAuthorizedUser;

    public function test_financial_administration_can_list_purchase_orders(): void
    {
        $this->withUserHavingRole(RoleName::FinancialAdministration);

        Livewire::test(ListPurchaseOrders::class)
            ->assertSuccessful();
    }

    public function test_can_create_purchase_order_with_lines(): void
    {
        $this->withAuthorizedUser();
        $costCenter = CostCenter::factory()->create();

        Livewire::test(CreatePurchaseOrder::class)
            ->fillForm([
                'creditor_name' => 'Acme Corp',
                'description' => 'Office supplies',
                'date' => '2026-06-26',
                'lines' => [
                    ['description' => 'Paper', 'price' => 100, 'price_vat' => 21, 'cost_center_id' => $costCenter->id],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('purchase_orders', [
            'creditor_name' => 'Acme Corp',
            'description' => 'Office supplies',
            'status' => PurchaseOrderStatus::Open->value,
        ]);
    }

    public function test_mark_as_pending_creates_bookkeeping_records(): void
    {
        $this->withAuthorizedUser();
        $costCenter = CostCenter::factory()->create();

        $po = PurchaseOrder::factory()
            ->has(PurchaseOrderLine::factory()->state(['cost_center_id' => $costCenter->id, 'price' => 100, 'price_vat' => 21]), 'lines')
            ->create(['status' => PurchaseOrderStatus::Open, 'date' => '2026-06-15']);

        Livewire::test(EditPurchaseOrder::class, ['record' => $po->getRouteKey()])
            ->callAction('markAsPending');

        $po->refresh();
        static::assertSame(PurchaseOrderStatus::Pending, $po->status);

        $this->assertDatabaseHas('bookkeeping_records', [
            'reference_type' => PurchaseOrder::class,
            'reference_id' => $po->id,
            'cost_center_id' => $costCenter->id,
            'year' => 2026,
        ]);
    }

    public function test_mark_as_paid_creates_bookkeeping_records(): void
    {
        $this->withAuthorizedUser();
        $costCenter = CostCenter::factory()->create();

        $po = PurchaseOrder::factory()
            ->has(PurchaseOrderLine::factory()->state(['cost_center_id' => $costCenter->id, 'price' => 100, 'price_vat' => 21]), 'lines')
            ->create(['status' => PurchaseOrderStatus::Pending, 'date' => '2026-06-15']);

        app(PurchaseOrderService::class)->markAsPaid(new PurchaseOrderIdList([PurchaseOrderId::create($po->id)]));

        $po->refresh();
        static::assertSame(PurchaseOrderStatus::Paid, $po->status);

        $this->assertDatabaseHas('bookkeeping_records', [
            'reference_type' => PurchaseOrder::class,
            'reference_id' => $po->id,
        ]);
    }

    public function test_cannot_edit_purchase_order_when_not_open(): void
    {
        $this->withAuthorizedUser();
        $po = PurchaseOrder::factory()->create(['status' => PurchaseOrderStatus::Pending]);

        static::assertFalse(Gate::allows('update', $po));
    }
}
