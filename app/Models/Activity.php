<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Pivots\ActivityMember;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property DateTimeInterface $start_date
 * @property DateTimeInterface $end_date
 */
#[Fillable(['name', 'description', 'billable_item_id', 'start_date', 'end_date'])]
final class Activity extends Model
{
    use HasFactory;

    /** @return BelongsTo<BillableItem, $this> */
    public function billableItem(): BelongsTo
    {
        return $this->belongsTo(BillableItem::class);
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
}
