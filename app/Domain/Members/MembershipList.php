<?php

declare(strict_types=1);

namespace App\Domain\Members;

use App\Domain\Invoices\Billing\BillableItemIdList;

final readonly class MembershipList
{
    /** @param Membership[] $memberships */
    public function __construct(
        private array $memberships,
    ) {}

    public function asBillingIdList(): BillableItemIdList
    {
        $ids = [];
        foreach ($this->memberships as $membership) {
            $ids[] = $membership->adultBillableItemId;
            $ids[] = $membership->kidsBillableItemId;
        }

        return new BillableItemIdList($ids);
    }
}
