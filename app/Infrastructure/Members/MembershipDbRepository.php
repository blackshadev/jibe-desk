<?php

declare(strict_types=1);

namespace App\Infrastructure\Members;

use App\Domain\Invoices\Billing\BillableItemId;
use App\Domain\Members\Membership;
use App\Domain\Members\MembershipId;
use App\Domain\Members\MembershipList;
use App\Domain\Members\MembershipRepository;

final class MembershipDbRepository implements MembershipRepository
{
    public function getById(MembershipId $membershipId): Membership
    {
        $model = \App\Models\Membership::findOrFail($membershipId->value);

        return new Membership(
            id: MembershipId::create($model->id),
            billableItemId: BillableItemId::create($model->billable_item_id),
        );
    }

    public function all(): MembershipList
    {
        $memberships = \App\Models\Membership::all()->map(
            fn (\App\Models\Membership $model): Membership => new Membership(
                id: MembershipId::create($model->id),
                billableItemId: BillableItemId::create($model->billable_item_id),
            )
        )->all();

        return new MembershipList($memberships);
    }
}
