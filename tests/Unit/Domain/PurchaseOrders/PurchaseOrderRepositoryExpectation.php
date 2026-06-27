<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\PurchaseOrders;

use App\Domain\PurchaseOrders\PurchaseOrderId;
use App\Domain\PurchaseOrders\PurchaseOrderRepository;
use Mockery;
use Mockery\MockInterface;

use function PHPUnit\Framework\equalTo;

final readonly class PurchaseOrderRepositoryExpectation
{
    private function __construct(
        public MockInterface&PurchaseOrderRepository $mock,
    ) {}

    public static function create(): self
    {
        return new self(Mockery::mock(PurchaseOrderRepository::class));
    }

    public function expectsMarkAsPending(PurchaseOrderId $id): void
    {
        $this->mock
            ->expects('markAsPending')
            ->with(equalTo($id));
    }

    public function expectsMarkAsPaid(PurchaseOrderId $id): void
    {
        $this->mock
            ->expects('markAsPaid')
            ->with(equalTo($id));
    }
}
