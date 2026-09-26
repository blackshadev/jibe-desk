<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\PurchaseOrders;

use App\Domain\PurchaseOrders\PurchaseOrderId;
use App\Domain\PurchaseOrders\PurchaseOrderProblem;
use App\Domain\PurchaseOrders\PurchaseOrderProblemType;
use Tests\UnitTestCase;

final class PurchaseOrderProblemTest extends UnitTestCase
{
    public function test_it_exposes_a_structured_problem(): void
    {
        $problem = new PurchaseOrderProblem(PurchaseOrderId::create(3), PurchaseOrderProblemType::MissingCostCenter, 2);

        static::assertSame(3, $problem->purchaseOrderId->value);
        static::assertSame(PurchaseOrderProblemType::MissingCostCenter, $problem->type);
        static::assertSame(2, $problem->lineNumber);
    }
}
