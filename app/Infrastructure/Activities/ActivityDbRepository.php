<?php

declare(strict_types=1);

namespace App\Infrastructure\Activities;

use App\Domain\Activities\Activity as ActivityEntity;
use App\Domain\Activities\ActivityId;
use App\Domain\Activities\ActivityRepository;
use App\Domain\Invoices\Billing\BillableItemId;
use App\Domain\Invoices\Billing\BillableItemInstanceId;
use App\Domain\Members\MemberId;
use App\Models\Activity;
use App\Models\Pivots\ActivityMember;

final class ActivityDbRepository implements ActivityRepository
{
    public function getById(ActivityId $activityId): ActivityEntity
    {
        $model = Activity::findOrFail($activityId->value);

        return new ActivityEntity(
            id: ActivityId::create($model->id),
            billableItemId: BillableItemId::create($model->billable_item_id),
            startDate: $model->start_date->toDateTimeImmutable(),
            endDate: $model->end_date?->toDateTimeImmutable(),
        );
    }

    public function attach(ActivityId $activityId, MemberId $memberId, BillableItemInstanceId $instanceId): void
    {
        ActivityMember::query()
            ->where([
                'activity_id' => $activityId->value,
                'member_id' => $memberId->value,
                'billable_item_instance_id' => null,
            ])->update([
                'billable_item_instance_id' => $instanceId->value,
            ]);
    }
}
