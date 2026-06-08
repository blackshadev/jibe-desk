<?php

declare(strict_types=1);

namespace App\Observers;

use App\Domain\Invoices\Billing\BillableItemInstanceId;
use App\Domain\Invoices\Billing\BillingItemApplicators\ApplyStorageSpaceRentalBilling;
use App\Domain\StorageSpaceRentals\StorageSpaceRentalId;
use App\Models\StorageSpaceRental;

final readonly class StorageSpaceRentalObserver
{
    public function __construct(private ApplyStorageSpaceRentalBilling $applyStorageSpaceRentalBilling)
    {
    }

    public function created(StorageSpaceRental $rental): void
    {
        $this->applyStorageSpaceRentalBilling->apply(
            StorageSpaceRentalId::create($rental->id),
        );
    }

    public function updated(StorageSpaceRental $rental): void
    {
        if ($rental->wasChanged('end_date') && $rental->billable_item_instance_id !== null) {
            $this->applyStorageSpaceRentalBilling->updateEndDate(
                BillableItemInstanceId::create($rental->billable_item_instance_id),
                $rental->end_date,
            );
        }
    }

    public function deleted(StorageSpaceRental $rental): void
    {
        if (!$rental->billable_item_instance_id) {
            return;
        }

        $this->applyStorageSpaceRentalBilling->stop(
            BillableItemInstanceId::create($rental->billable_item_instance_id)
        );
    }
}
