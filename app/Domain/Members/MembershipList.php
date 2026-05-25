<?php

declare(strict_types=1);

namespace App\Domain\Members;

use App\Domain\Invoices\Billing\BillableItemId;
use App\Domain\Invoices\Billing\BillableItemIdList;

final readonly class MembershipList
{
    /** @param Membership[] $memberships */
    public function __construct(private array $memberships)
    {
    }

    public function asBillingIdList(): BillableItemIdList
    {
        return new BillableItemIdList(
            array_map(
                static fn (Membership $membership): BillableItemId => $membership->billableItemId,
                $this->memberships,
            )
        );
    }
}
