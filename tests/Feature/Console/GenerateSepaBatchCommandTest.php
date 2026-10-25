<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Domain\Billing\PurchaseOrders\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\SepaBatch;
use Illuminate\Support\Facades\Bus;
use Tests\FeatureTestCase;

final class GenerateSepaBatchCommandTest extends FeatureTestCase
{
    public function test_command_sweeps_the_batch_months_purchase_orders_into_the_batch(): void
    {
        Bus::fake();

        $purchaseOrder = PurchaseOrder::factory()->accepted()->createQuietly(['date' => '2026-05-10']);

        $this->artisan('app:generate-sepa-batch', ['date' => '2026-05-25'])->assertSuccessful();

        $batch = SepaBatch::query()->firstOrFail();

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $purchaseOrder->id,
            'sepa_batch_id' => $batch->id,
            'status' => PurchaseOrderStatus::Pending->value,
        ]);
    }

    public function test_command_ignores_purchase_orders_outside_the_batch_month(): void
    {
        Bus::fake();

        $purchaseOrder = PurchaseOrder::factory()->accepted()->createQuietly(['date' => '2026-06-05']);

        $this->artisan('app:generate-sepa-batch', ['date' => '2026-05-25'])->assertSuccessful();

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $purchaseOrder->id,
            'sepa_batch_id' => null,
            'status' => PurchaseOrderStatus::Open->value,
        ]);
    }
}
