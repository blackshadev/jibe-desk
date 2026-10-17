<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Billing\PurchaseOrders;

use App\Domain\Billing\PurchaseOrders\PurchaseOrderCompletenessService;
use App\Domain\Billing\PurchaseOrders\PurchaseOrderIdList;
use App\Domain\Billing\PurchaseOrders\PurchaseOrderProblems;
use Mockery;
use Mockery\MockInterface;

final readonly class PurchaseOrderCompletenessServiceExpectation
{
    private function __construct(
        public MockInterface&PurchaseOrderCompletenessService $mock,
    ) {}

    public static function create(): self
    {
        return new self(Mockery::mock(PurchaseOrderCompletenessService::class));
    }

    public function expectsFindProblems(PurchaseOrderIdList $ids, PurchaseOrderProblems $return): void
    {
        $this->mock
            ->expects('findProblems')
            ->with($ids)
            ->andReturn($return);
    }

    public function neverExpectsFindProblems(): void
    {
        $this->mock->expects('findProblems')->never();
    }
}
