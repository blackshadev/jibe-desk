<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Domain\PurchaseOrders\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use Tests\FeatureTestCase;

final class PurchaseOrderTest extends FeatureTestCase
{
    public function test_open_or_pending_scope_filters_correct_statuses(): void
    {
        PurchaseOrder::factory()->open()->createQuietly();
        PurchaseOrder::factory()->pending()->createQuietly();
        PurchaseOrder::factory()->paid()->createQuietly();

        $results = PurchaseOrder::query()->openOrPending()->get();

        static::assertCount(2, $results);
        static::assertTrue($results->contains('status', PurchaseOrderStatus::Open));
        static::assertTrue($results->contains('status', PurchaseOrderStatus::Pending));
        static::assertFalse($results->contains('status', PurchaseOrderStatus::Paid));
    }

    public function test_order_by_relevancy_scope_orders_by_iban_then_amount(): void
    {
        $iban = 'NL00ABCD1234567890';

        $po1 = PurchaseOrder::factory()
            ->open()
            ->has(PurchaseOrderLine::factory()->state(['price' => 105.00]), 'lines')
            ->createQuietly(['creditor_iban' => 'NL99XXXX0000000000']);

        $po2 = PurchaseOrder::factory()
            ->open()
            ->has(PurchaseOrderLine::factory()->state(['price' => 150.00]), 'lines')
            ->createQuietly(['creditor_iban' => $iban]);

        $po3 = PurchaseOrder::factory()
            ->open()
            ->has(PurchaseOrderLine::factory()->state(['price' => 90.00]), 'lines')
            ->createQuietly(['creditor_iban' => $iban]);

        $results = PurchaseOrder::query()
            ->openOrPending()
            ->orderByRelevancy(100.00, $iban)
            ->pluck('id')
            ->all();

        static::assertSame([$po3->id, $po2->id, $po1->id], $results);
    }

    public function test_order_by_relevancy_handles_null_creditor_iban(): void
    {
        $iban = 'NL00ABCD1234567890';

        $po1 = PurchaseOrder::factory()
            ->open()
            ->has(PurchaseOrderLine::factory()->state(['price' => 500.00]), 'lines')
            ->createQuietly(['creditor_iban' => null]);

        $po2 = PurchaseOrder::factory()
            ->open()
            ->has(PurchaseOrderLine::factory()->state(['price' => 100.00]), 'lines')
            ->createQuietly(['creditor_iban' => $iban]);

        $results = PurchaseOrder::query()
            ->openOrPending()
            ->orderByRelevancy(100.00, $iban)
            ->pluck('id')
            ->all();

        static::assertSame([$po2->id, $po1->id], $results);
    }
}
