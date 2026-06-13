<?php

declare(strict_types=1);

namespace App\Domain\StorageSpaceRentals;

use App\Domain\Invoices\Billing\BillableItemId;
use App\Domain\Members\MemberId;
use DateTimeInterface;

final readonly class StorageSpaceRental
{
    public function __construct(
        public StorageSpaceRentalId $id,
        public MemberId $memberId,
        public BillableItemId $billableItemId,
        public DateTimeInterface $startDate,
        public ?DateTimeInterface $endDate,
    ) {}
}
