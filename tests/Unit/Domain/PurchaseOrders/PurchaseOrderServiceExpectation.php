<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\PurchaseOrders;

use App\Domain\PurchaseOrders\PurchaseOrderId;
use App\Domain\PurchaseOrders\PurchaseOrderIdList;
use App\Domain\PurchaseOrders\PurchaseOrderService;
use Mockery;
use Mockery\MockInterface;

use function PHPUnit\Framework\equalTo;

final readonly class PurchaseOrderServiceExpectation
{
    private function __construct(
        public MockInterface&PurchaseOrderService $mock,
    ) {}

    public static function create(): self
    {
        return new self(Mockery::mock(PurchaseOrderService::class));
    }

    public function expectsMarkAsPaid(PurchaseOrderIdList $ids): void
    {
        $this->mock
            ->expects('markAsPaid')
            ->with(equalTo($ids));
    }

    public function expectsMarkAsPending(PurchaseOrderId $id): void
    {
        $this->mock
            ->expects('markAsPending')
            ->with(equalTo($id));
    }
}
