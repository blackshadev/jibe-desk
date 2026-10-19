<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Billing\PurchaseOrders;

use App\Domain\Billing\PurchaseOrders\PurchaseOrderCompleteness;
use App\Domain\Billing\PurchaseOrders\PurchaseOrderId;
use App\Domain\Billing\PurchaseOrders\PurchaseOrderIdList;
use App\Domain\Billing\PurchaseOrders\PurchaseOrderRepository;
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

    /**
     * @param list<PurchaseOrderCompleteness> $return
     */
    public function expectsGetCompleteness(PurchaseOrderIdList $ids, array $return): void
    {
        $this->mock
            ->expects('getCompleteness')
            ->with(equalTo($ids))
            ->andReturn($return);
    }

    public function expectsMarkAsApprovalAccepted(PurchaseOrderIdList $ids): void
    {
        $this->mock
            ->expects('markAsApprovalAccepted')
            ->with(equalTo($ids));
    }

    public function expectsMarkAsApprovalDeclined(PurchaseOrderIdList $ids, ?string $reason = null): void
    {
        $this->mock
            ->expects('markAsApprovalDeclined')
            ->with(equalTo($ids), equalTo($reason));
    }

    public function expectsMarkAsPaymentPending(PurchaseOrderIdList $ids): void
    {
        $this->mock
            ->expects('markAsPaymentPending')
            ->with(equalTo($ids));
    }

    public function expectsMarkAsPaid(PurchaseOrderIdList $ids): void
    {
        $this->mock
            ->expects('markAsPaid')
            ->with(equalTo($ids));
    }

    public function expectsHasCompletedTransactions(PurchaseOrderId $id, bool $return = true): void
    {
        $this->mock
            ->expects('hasCompletedTransactions')
            ->with(equalTo($id))
            ->andReturn($return);
    }

    public function expectsMarkAsPaymentDeclined(PurchaseOrderIdList $ids, ?string $reason = null): void
    {
        $this->mock
            ->expects('markAsPaymentDeclined')
            ->with(equalTo($ids), equalTo($reason));
    }

    public function expectsReopenPayment(PurchaseOrderIdList $ids): void
    {
        $this->mock
            ->expects('reopenPayment')
            ->with(equalTo($ids));
    }

    public function expectsFindMatchingDebit(string $creditorIban, float $amount, DateTimeInterface $date, ?PurchaseOrderId $return): void
    {
        $this->mock
            ->expects('findMatchingDebit')
            ->with(equalTo($creditorIban), equalTo($amount), equalTo($date))
            ->andReturn($return);
    }

    public function neverExpectsMarkAsApprovalAccepted(): void
    {
        $this->mock->expects('markAsApprovalAccepted')->never();
    }

    public function neverExpectsMarkAsPaymentPending(): void
    {
        $this->mock->expects('markAsPaymentPending')->never();
    }

    public function neverExpectsMarkAsPaid(): void
    {
        $this->mock->expects('markAsPaid')->never();
    }
}
