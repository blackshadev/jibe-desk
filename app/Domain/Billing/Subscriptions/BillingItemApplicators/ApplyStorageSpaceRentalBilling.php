<?php

declare(strict_types=1);

namespace App\Domain\Billing\Subscriptions\BillingItemApplicators;

use App\Domain\Billing\Subscriptions\BillableItemInstanceId;
use App\Domain\StorageSpaceRentals\StorageSpaceRentalId;
use DateTimeInterface;
use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface ApplyStorageSpaceRentalBilling
{
    public function apply(StorageSpaceRentalId $rentalId): void;

    public function updateEndDate(BillableItemInstanceId $billableItemInstanceId, ?DateTimeInterface $endDate): void;

    public function stop(BillableItemInstanceId $billableItemInstanceId): void;
}
