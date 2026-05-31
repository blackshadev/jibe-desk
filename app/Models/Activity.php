<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Pivots\ActivityMember;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property CarbonInterface $start_date
 * @property ?CarbonInterface $end_date
 */
#[Fillable(['name', 'description', 'billable_item_id', 'start_date', 'end_date'])]
final class Activity extends Model
{
    use HasFactory;

    /** @return BelongsTo<BillableItem, $this> */
    public function billableItem(): BelongsTo
    {
        return $this
            ->belongsTo(BillableItem::class)
            ->withDefault();
    }

    /** @return BelongsToMany<Member, $this, ActivityMember> */
    public function members(): BelongsToMany
    {
        return $this
            ->belongsToMany(Member::class)
            ->using(ActivityMember::class)
            ->withPivot('billable_item_instance_id')
            ->withTimestamps();
    }

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->whereNull('end_date')->orWhereFuture('end_date');
    }

    #[Scope]
    protected function inactive(Builder $query): Builder
    {
        return $query->orWhereNowOrPast('end_date');
    }
}
