<?php

declare(strict_types=1);

namespace App\Domain\Members;

use App\Domain\Invoices\Billing\BillableItemId;

final readonly class Membership
{
    public function __construct(
        public MembershipId $id,
        public BillableItemId $adultBillableItemId,
        public BillableItemId $kidsBillableItemId,
    ) {
    }
}
