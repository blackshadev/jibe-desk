<?php

declare(strict_types=1);

namespace App\Infrastructure\StorageSpaceRentals;

use App\Domain\Invoices\Billing\BillableItemId;
use App\Domain\Invoices\Billing\BillableItemInstanceId;
use App\Domain\Members\MemberId;
use App\Domain\StorageSpaceRentals\StorageSpaceRental as StorageSpaceRentalEntity;
use App\Domain\StorageSpaceRentals\StorageSpaceRentalId;
use App\Domain\StorageSpaceRentals\StorageSpaceRentalRepository;
use App\Models\StorageSpaceRental;
use Override;

final class StorageSpaceRentalDbRepository implements StorageSpaceRentalRepository
{
    #[Override]
    public function getById(StorageSpaceRentalId $rentalId): StorageSpaceRentalEntity
    {
        $model = StorageSpaceRental::query()->with('storageSpace.location.billableItem')->findOrFail($rentalId->value);

        return new StorageSpaceRentalEntity(
            id: StorageSpaceRentalId::create($model->id),
            memberId: MemberId::create($model->member_id),
            billableItemId: BillableItemId::create($model->storageSpace->location->billable_item_id),
            startDate: $model->start_date->toDateTimeImmutable(),
            endDate: $model->end_date?->toDateTimeImmutable(),
        );
    }

    #[Override]
    public function attachBillableItemInstance(StorageSpaceRentalId $rentalId, BillableItemInstanceId $instanceId): void
    {
        StorageSpaceRental::query()
            ->where('id', $rentalId->value)
            ->update(['billable_item_instance_id' => $instanceId->value]);
    }
}
