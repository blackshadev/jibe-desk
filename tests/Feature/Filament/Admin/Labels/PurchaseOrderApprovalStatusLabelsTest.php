<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Admin\Labels;

use App\Domain\Billing\PurchaseOrders\PurchaseOrderApprovalStatus;
use App\Filament\Admin\Labels\PurchaseOrderApprovalStatusLabels;
use Tests\FeatureTestCase;

final class PurchaseOrderApprovalStatusLabelsTest extends FeatureTestCase
{
    public function test_it_maps_every_purchase_order_approval_status_to_a_label(): void
    {
        $options = PurchaseOrderApprovalStatusLabels::options();

        static::assertCount(3, $options);
        static::assertSame('Wacht op goedkeuring', $options[PurchaseOrderApprovalStatus::Pending->value]);
        static::assertSame('Goedgekeurd', $options[PurchaseOrderApprovalStatus::Accepted->value]);
        static::assertSame('Afgewezen', $options[PurchaseOrderApprovalStatus::Declined->value]);
    }
}
