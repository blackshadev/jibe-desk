<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\PurchaseOrders;

use App\Domain\PurchaseOrders\PurchaseOrderId;
use App\Domain\PurchaseOrders\PurchaseOrderIncompleteException;
use App\Domain\PurchaseOrders\PurchaseOrderProblem;
use App\Domain\PurchaseOrders\PurchaseOrderProblems;
use App\Domain\PurchaseOrders\PurchaseOrderProblemType;
use Tests\UnitTestCase;

final class PurchaseOrderProblemsTest extends UnitTestCase
{
    public function test_it_has_no_problems_by_default(): void
    {
        $problems = new PurchaseOrderProblems();

        static::assertSame([], $problems->problems);
    }

    public function test_it_exposes_the_problems_it_was_built_with(): void
    {
        $problem = new PurchaseOrderProblem(PurchaseOrderId::create(1), PurchaseOrderProblemType::MissingCreditorName);

        $problems = new PurchaseOrderProblems([$problem]);

        static::assertSame([$problem], $problems->problems);
    }

    public function test_it_asserts_complete_without_problems(): void
    {
        $problems = new PurchaseOrderProblems();

        $problems->assertComplete();

        static::assertSame([], $problems->problems);
    }

    public function test_it_throws_when_it_has_problems(): void
    {
        $problems = new PurchaseOrderProblems([
            new PurchaseOrderProblem(PurchaseOrderId::create(1), PurchaseOrderProblemType::MissingCostCenter, 1),
        ]);

        try {
            $problems->assertComplete();
            static::fail('Expected PurchaseOrderIncompleteException was not thrown.');
        } catch (PurchaseOrderIncompleteException $exception) {
            static::assertSame($problems->problems, $exception->problems);
        }
    }
}
