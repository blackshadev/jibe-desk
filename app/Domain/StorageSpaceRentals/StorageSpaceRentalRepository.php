<?php

declare(strict_types=1);

namespace App\Domain\StorageSpaceRentals;

use App\Domain\Invoices\Billing\BillableItemInstanceId;
use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface StorageSpaceRentalRepository
{
    public function getById(StorageSpaceRentalId $rentalId): StorageSpaceRental;

    public function attachBillableItemInstance(StorageSpaceRentalId $rentalId, BillableItemInstanceId $instanceId): void;
}
