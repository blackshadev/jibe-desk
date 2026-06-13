<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

#[Fillable(['member_id', 'billable_item_id', 'start_date', 'end_date', 'bill_cycle_in_months'])]
final class BillableItemInstance extends Model
{
    use HasFactory;

    /** @return BelongsTo<BillableItem, $this> */
    public function billableItem(): BelongsTo
    {
        return $this->belongsTo(BillableItem::class);
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function stop(): void
    {
        $this->update(['end_date' => now()]);
    }

    public function isStopped(): bool
    {
        return $this->end_date !== null;
    }

    public function resume(): void
    {
        $this->update(['end_date' => null]);
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    #[Scope]
    protected function _active(Builder $query): Builder
    {
        return $query->whereNull('end_date')->orWhereFuture('end_date');
    }

    #[Scope]
    protected function _inactive(Builder $query): Builder
    {
        return $query->whereNowOrPast('end_date');
    }
}
