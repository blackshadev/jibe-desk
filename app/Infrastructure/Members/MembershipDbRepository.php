<?php

declare(strict_types=1);

namespace App\Infrastructure\Members;

use App\Domain\Invoices\Billing\BillableItemId;
use App\Domain\Members\Membership;
use App\Domain\Members\MembershipId;
use App\Domain\Members\MembershipList;
use App\Domain\Members\MembershipRepository;
use App\Models\Membership as MembershipModel;

final class MembershipDbRepository implements MembershipRepository
{
    public function getById(MembershipId $membershipId): Membership
    {
        $model = MembershipModel::findOrFail($membershipId->value);

        return new Membership(
            id: MembershipId::create($model->id),
            adultBillableItemId: BillableItemId::create($model->adult_billable_item_id),
            kidsBillableItemId: BillableItemId::create($model->kids_billable_item_id),
        );
    }

    public function all(): MembershipList
    {
        $memberships = MembershipModel::all()->map(
            static fn (MembershipModel $model): Membership => new Membership(
                id: MembershipId::create($model->id),
                adultBillableItemId: BillableItemId::create($model->adult_billable_item_id),
                kidsBillableItemId: BillableItemId::create($model->kids_billable_item_id),
            )
        )->all();

        return new MembershipList($memberships);
    }
}
