<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\PurchaseOrders;

use App\Domain\PurchaseOrders\PurchaseOrderId;
use App\Domain\PurchaseOrders\PurchaseOrderIncompleteException;
use App\Domain\PurchaseOrders\PurchaseOrderProblem;
use App\Domain\PurchaseOrders\PurchaseOrderProblemType;
use Tests\UnitTestCase;

final class PurchaseOrderIncompleteExceptionTest extends UnitTestCase
{
    public function test_it_exposes_incomplete_purchase_order_problems(): void
    {
        $problem = new PurchaseOrderProblem(PurchaseOrderId::create(1), PurchaseOrderProblemType::MissingCreditorIban);
        $exception = new PurchaseOrderIncompleteException([$problem]);

        static::assertSame([$problem], $exception->problems);
        static::assertSame('Purchase order is incomplete and cannot change status.', $exception->getMessage());
    }
}
