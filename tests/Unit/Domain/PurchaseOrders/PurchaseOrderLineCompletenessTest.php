<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\PurchaseOrders;

use App\Domain\PurchaseOrders\PurchaseOrderLineCompleteness;
use Tests\UnitTestCase;

final class PurchaseOrderLineCompletenessTest extends UnitTestCase
{
    public function test_it_exposes_cost_center_facts(): void
    {
        $completeness = new PurchaseOrderLineCompleteness(7);

        static::assertSame(7, $completeness->costCenterId);
    }
}
