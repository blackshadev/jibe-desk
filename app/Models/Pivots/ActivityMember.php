<?php

declare(strict_types=1);

namespace App\Models\Pivots;

use App\Models\Activity;
use App\Models\BillableItemInstance;
use App\Models\Member;
use App\Observers\ActivityMemberObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

#[Fillable(['activity_id', 'member_id', 'billable_item_instance_id'])]
#[ObservedBy([ActivityMemberObserver::class])]
final class ActivityMember extends Pivot
{
    use HasTimestamps;

    public $incrementing = true;

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** @return BelongsTo<Activity, $this> */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    /** @return BelongsTo<BillableItemInstance, $this> */
    public function billableInstance(): BelongsTo
    {
        return $this->belongsTo(BillableItemInstance::class);
    }
}
