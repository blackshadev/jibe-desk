<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\StorageSpaceRentals;

use App\Domain\Invoices\Billing\BillableItemInstanceId;
use App\Domain\StorageSpaceRentals\StorageSpaceRentalId;
use App\Domain\StorageSpaceRentals\StorageSpaceRentalRepository;
use Mockery;
use Mockery\MockInterface;

use function PHPUnit\Framework\equalTo;

final readonly class StorageSpaceRentalRepositoryExpectation
{
    private function __construct(
        public MockInterface&StorageSpaceRentalRepository $mock,
    ) {}

    public static function create(): self
    {
        return new self(Mockery::mock(StorageSpaceRentalRepository::class));
    }

    public function expectsAttachBillableItemInstance(StorageSpaceRentalId $rentalId, BillableItemInstanceId $instanceId): void
    {
        $this->mock
            ->expects('attachBillableItemInstance')
            ->with(equalTo($rentalId), equalTo($instanceId));
    }
}
