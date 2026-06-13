<?php

declare(strict_types=1);

namespace Tests\Feature\Observers;

use App\Domain\Invoices\Billing\BillableItemInstanceId;
use App\Domain\StorageSpaceRentals\StorageSpaceRentalId;
use App\Models\BillableItem;
use App\Models\BillableItemInstance;
use App\Models\Member;
use App\Models\StorageSpaceRental;
use App\Observers\StorageSpaceRentalObserver;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Override;
use Tests\FeatureTestCase;
use Tests\Unit\Domain\Invoices\Billing\BillingItemApplicators\ApplyStorageSpaceRentalBillingExpectation;

final class StorageSpaceRentalObserverTest extends FeatureTestCase
{
    private ApplyStorageSpaceRentalBillingExpectation $applyBilling;

    private StorageSpaceRentalObserver $subject;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->applyBilling = ApplyStorageSpaceRentalBillingExpectation::create();

        $this->subject = new StorageSpaceRentalObserver($this->applyBilling->mock);
    }

    public function test_it_applies_billing_when_rental_is_created(): void
    {
        $rental = $this->createRental(null);
        $rentalId = StorageSpaceRentalId::create($rental->id);

        $this->applyBilling->expectsApply($rentalId);

        $this->subject->created($rental);
    }

    public function test_it_updates_instance_end_date_when_rental_end_date_changes(): void
    {
        $newEndDate = CarbonImmutable::create('2027-01-01');
        $rental = $this->createRental(new CarbonImmutable('2026-01-01'));
        $billableItemInstanceId = BillableItemInstanceId::create($rental->billable_item_instance_id);

        $rental->end_date = $newEndDate;
        $rental->saveQuietly();

        $this->applyBilling->expectsUpdateEndDate(
            $billableItemInstanceId,
            $newEndDate,
        );

        $this->subject->updated($rental);
    }

    public function test_it_does_not_update_instance_when_end_date_unchanged(): void
    {
        $rental = $this->createRental(CarbonImmutable::create('2027-01-01'));

        $this->applyBilling->expectsUpdateEndDateNever();

        $this->subject->updated($rental);
    }

    public function test_it_does_not_update_instance_when_no_billable_item_instance(): void
    {
        $newEndDate = CarbonImmutable::create('2027-01-01');
        $member = Member::factory()->createOneQuietly();
        $rental = StorageSpaceRental::factory()
            ->for($member)
            ->create([
                'end_date' => null,
            ]);

        $rental->end_date = $newEndDate;
        $rental->saveQuietly();

        $this->applyBilling->expectsUpdateEndDateNever();

        $this->subject->updated($rental);
    }

    public function test_it_stops_billing_when_rental_is_deleted_with_instance(): void
    {
        $rental = $this->createRental(new CarbonImmutable('2027-01-01'));
        $billableItemInstanceId = BillableItemInstanceId::create($rental->billable_item_instance_id);

        $this->applyBilling->expectsStop($billableItemInstanceId);

        $this->subject->deleted($rental);
    }

    public function test_it_does_not_stop_billing_when_rental_deleted_without_instance(): void
    {
        $rental = new StorageSpaceRental([
            'billable_item_instance_id' => null,
        ]);

        $this->applyBilling->expectsStopNever();

        $this->subject->deleted($rental);
    }

    private function createRental(?DateTimeInterface $endDate): StorageSpaceRental
    {
        $member = Member::factory()->createOneQuietly();

        $billableItemInstance = BillableItemInstance::factory()
            ->for(BillableItem::factory())
            ->for($member)
            ->create();

        return StorageSpaceRental::factory()
            ->for($billableItemInstance)
            ->for($member)
            ->createQuietly([
                'end_date' => $endDate,
            ]);
    }
}
