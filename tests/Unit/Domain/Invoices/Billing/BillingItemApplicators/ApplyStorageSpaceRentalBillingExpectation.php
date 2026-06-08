<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices\Billing\BillingItemApplicators;

use App\Domain\Invoices\Billing\BillableItemInstanceId;
use App\Domain\Invoices\Billing\BillingItemApplicators\ApplyStorageSpaceRentalBilling;
use App\Domain\StorageSpaceRentals\StorageSpaceRentalId;
use DateTimeInterface;
use Mockery;
use Mockery\MockInterface;
use function PHPUnit\Framework\equalTo;

final readonly class ApplyStorageSpaceRentalBillingExpectation
{
    private function __construct(public MockInterface&ApplyStorageSpaceRentalBilling $mock)
    {
    }

    public static function create(): self
    {
        return new self(Mockery::mock(ApplyStorageSpaceRentalBilling::class));
    }

    public function expectsApply(StorageSpaceRentalId $rentalId): void
    {
        $this->mock
            ->expects('apply')
            ->with(equalTo($rentalId))
            ->andReturnNull();
    }

    public function expectsUpdateEndDate(BillableItemInstanceId $instanceId, ?DateTimeInterface $endDate): void
    {
        $this->mock
            ->expects('updateEndDate')
            ->with(equalTo($instanceId), equalTo($endDate));
    }

    public function expectsStop(BillableItemInstanceId $instanceId): void
    {
        $this->mock
            ->expects('stop')
            ->with(equalTo($instanceId));
    }

    public function expectsStopNever(): void
    {
        $this->mock
            ->expects('stop')
            ->never();
    }

    public function expectsUpdateEndDateNever(): void
    {
        $this->mock
            ->expects('updateEndDate')
            ->never();
    }
}
