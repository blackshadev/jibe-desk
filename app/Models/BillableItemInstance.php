<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * @property CarbonInterface $start_date
 * @property ?CarbonInterface $end_date
 */
#[Fillable(['member_id', 'billable_item_id', 'start_date', 'end_date', 'bill_cycle_in_months', 'bill_month'])]
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

    public function quantityFor(DateTimeInterface $when): float
    {
        $cycle = (int) $this->bill_cycle_in_months;

        if ($cycle <= 1) {
            return 1.0;
        }

        if ($this->start_date->startOfMonth()->gt($when)) {
            return 0.0;
        }

        $invoiceMonth = CarbonImmutable::create($when)->firstOfMonth();

        $month = (int) $invoiceMonth->format('n');
        $billMonth = (int) $this->bill_month;

        $offset = (($month - $billMonth + 12) % 12) % $cycle;
        $anchor = $invoiceMonth->subMonths($offset);
        $nextBill = $anchor->addMonths($cycle);

        $coverage = $anchor->max($this->start_date->firstOfMonth());
        $months = max(1, min($cycle, abs($nextBill->diffInMonths($coverage))));

        return $months / $cycle;
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'bill_month' => 'integer',
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
        return $query->whereNowOrPast('end_date');
    }
}
