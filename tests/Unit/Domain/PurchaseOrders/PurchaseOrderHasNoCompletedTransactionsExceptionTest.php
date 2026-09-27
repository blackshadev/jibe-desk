<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\PurchaseOrders;

use App\Domain\PurchaseOrders\PurchaseOrderHasNoCompletedTransactionsException;
use App\Domain\PurchaseOrders\PurchaseOrderId;
use RuntimeException;
use Tests\UnitTestCase;

final class PurchaseOrderHasNoCompletedTransactionsExceptionTest extends UnitTestCase
{
    public function test_it_exposes_the_purchase_order_id(): void
    {
        $purchaseOrderId = PurchaseOrderId::create(42);

        $exception = new PurchaseOrderHasNoCompletedTransactionsException($purchaseOrderId);

        static::assertSame($purchaseOrderId, $exception->purchaseOrderId);
        static::assertSame('Purchase order 42 has no completed linked transactions', $exception->getMessage());
        static::assertInstanceOf(RuntimeException::class, $exception);
    }
}
