<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices\Billing\BillingItemApplicators;

use App\Domain\Invoices\Billing\BillableItemId;
use App\Domain\Invoices\Billing\BillableItemInstanceId;
use App\Domain\Invoices\Billing\BillingItemApplicators\ApplyStorageSpaceRentalBillingImpl;
use App\Domain\Members\MemberId;
use App\Domain\StorageSpaceRentals\StorageSpaceRental;
use App\Domain\StorageSpaceRentals\StorageSpaceRentalId;
use Carbon\CarbonImmutable;
use Override;
use Tests\Unit\Domain\Clock\ClockExpectation;
use Tests\Unit\Domain\Invoices\Billing\BillableItemInstanceRepositoryExpectation;
use Tests\Unit\Domain\StorageSpaceRentals\StorageSpaceRentalRepositoryExpectation;
use Tests\UnitTestCase;

final class ApplyStorageSpaceRentalBillingImplTest extends UnitTestCase
{
    private StorageSpaceRentalRepositoryExpectation $rentalRepo;
    private BillableItemInstanceRepositoryExpectation $instanceRepo;
    private ApplyStorageSpaceRentalBillingImpl $subject;
    private ClockExpectation $clock;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->rentalRepo = StorageSpaceRentalRepositoryExpectation::create();
        $this->instanceRepo = BillableItemInstanceRepositoryExpectation::create();
        $this->clock = ClockExpectation::create();

        $this->subject = new ApplyStorageSpaceRentalBillingImpl(
            $this->rentalRepo->mock,
            $this->instanceRepo->mock,
            $this->clock->mock,
        );
    }

    public function test_apply_creates_instance_and_attaches(): void
    {
        $rentalId = StorageSpaceRentalId::create(42);
        $memberId = MemberId::create(1);
        $billableItemId = BillableItemId::create(10);
        $startDate = CarbonImmutable::parse('2026-01-01');
        $endDate = CarbonImmutable::parse('2026-12-31');
        $instanceId = BillableItemInstanceId::create(99);

        $rental = new StorageSpaceRental(
            id: $rentalId,
            memberId: $memberId,
            billableItemId: $billableItemId,
            startDate: $startDate,
            endDate: $endDate,
        );

        $this->rentalRepo->expectsGetById($rentalId, $rental);

        $this->instanceRepo->expectsAdd($memberId, 10, $endDate, $startDate, $instanceId);
        $this->rentalRepo->expectsAttachBillableItemInstance($rentalId, $instanceId);

        $this->subject->apply($rentalId);
    }

    public function test_update_end_date_delegates_to_instance_repo(): void
    {
        $instanceId = BillableItemInstanceId::create(99);
        $newEndDate = CarbonImmutable::parse('2026-06-30');

        $this->instanceRepo->expectsUpdateEndDate($instanceId, $newEndDate);

        $this->subject->updateEndDate($instanceId, $newEndDate);
    }

    public function test_stop_delegates_to_instance_repo(): void
    {
        $instanceId = BillableItemInstanceId::create(99);
        $now = CarbonImmutable::parse('2023-06-15');

        $this->clock->expectsNow($now);
        $this->instanceRepo->expectsStop($instanceId, $now);

        $this->subject->stop($instanceId);
    }
}
