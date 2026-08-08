<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\PurchaseOrders;

use App\Domain\PurchaseOrders\PurchaseOrderId;
use App\Domain\PurchaseOrders\PurchaseOrderIdList;
use App\Domain\PurchaseOrders\PurchaseOrderRepository;
use DateTimeInterface;
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

    public function expectsMarkAsPending(PurchaseOrderIdList $ids): void
    {
        $this->mock
            ->expects('markAsPending')
            ->with(equalTo($ids));
    }

    public function expectsMarkAsPaid(PurchaseOrderIdList $ids): void
    {
        $this->mock
            ->expects('markAsPaid')
            ->with(equalTo($ids));
    }

    public function expectsFindMatchingDebit(string $creditorIban, float $amount, DateTimeInterface $date, ?PurchaseOrderId $return): void
    {
        $this->mock
            ->expects('findMatchingDebit')
            ->with(equalTo($creditorIban), equalTo($amount), equalTo($date))
            ->andReturn($return);
    }
}
