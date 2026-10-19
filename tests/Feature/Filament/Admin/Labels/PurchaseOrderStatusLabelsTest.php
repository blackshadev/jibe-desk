<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Admin\Labels;

use App\Domain\Billing\PurchaseOrders\PurchaseOrderStatus;
use App\Filament\Admin\Labels\PurchaseOrderStatusLabels;
use Tests\FeatureTestCase;

final class PurchaseOrderStatusLabelsTest extends FeatureTestCase
{
    public function test_it_maps_every_purchase_order_status_to_a_label(): void
    {
        $options = PurchaseOrderStatusLabels::options();

        static::assertCount(5, $options);
        static::assertSame('Open', $options[PurchaseOrderStatus::Open->value]);
        static::assertSame('In behandeling', $options[PurchaseOrderStatus::Pending->value]);
        static::assertSame('Betaald', $options[PurchaseOrderStatus::Paid->value]);
        static::assertSame('Geweigerd', $options[PurchaseOrderStatus::Declined->value]);
        static::assertSame('Geannuleerd', $options[PurchaseOrderStatus::Cancelled->value]);
    }
}
