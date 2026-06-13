<?php

declare(strict_types=1);

namespace App\Domain\Invoices\Billing\BillingItemApplicators;

use App\Domain\Invoices\Billing\BillableItemInstanceId;
use App\Domain\Invoices\Billing\BillableItemInstanceRepository;
use App\Domain\StorageSpaceRentals\StorageSpaceRentalId;
use App\Domain\StorageSpaceRentals\StorageSpaceRentalRepository;
use DateTimeInterface;
use Override;

final readonly class ApplyStorageSpaceRentalBillingImpl implements ApplyStorageSpaceRentalBilling
{
    public function __construct(
        private StorageSpaceRentalRepository $storageSpaceRentalRepository,
        private BillableItemInstanceRepository $billableItemInstanceRepository,
    ) {}

    #[Override]
    public function apply(StorageSpaceRentalId $rentalId): void
    {
        $rental = $this->storageSpaceRentalRepository->getById($rentalId);

        $instanceId = $this->billableItemInstanceRepository->add(
            $rental->memberId,
            $rental->billableItemId,
            $rental->endDate,
            $rental->startDate,
        );

        $this->storageSpaceRentalRepository->attachBillableItemInstance($rentalId, $instanceId);
    }

    #[Override]
    public function updateEndDate(BillableItemInstanceId $billableItemInstanceId, ?DateTimeInterface $endDate): void
    {
        $this->billableItemInstanceRepository->updateEndDate($billableItemInstanceId, $endDate);
    }

    #[Override]
    public function stop(BillableItemInstanceId $billableItemInstanceId): void
    {
        $this->billableItemInstanceRepository->stop($billableItemInstanceId);
    }
}
