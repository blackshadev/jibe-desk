<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\PurchaseOrders;

use App\Domain\PurchaseOrders\PurchaseOrderCompleteness;
use App\Domain\PurchaseOrders\PurchaseOrderId;
use Tests\UnitTestCase;

final class PurchaseOrderCompletenessTest extends UnitTestCase
{
    public function test_it_exposes_purchase_order_completeness_facts(): void
    {
        $completeness = new PurchaseOrderCompleteness(PurchaseOrderId::create(1), 'Acme', 'NL02ABNA0123456789', []);

        static::assertSame(1, $completeness->id->value);
        static::assertSame('Acme', $completeness->creditorName);
        static::assertSame('NL02ABNA0123456789', $completeness->creditorIban);
        static::assertSame([], $completeness->lines);
    }
}
