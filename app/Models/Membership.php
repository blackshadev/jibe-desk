<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'adult_billable_item_id', 'kids_billable_item_id'])]
final class Membership extends Model
{
    use HasFactory;

    /** @return HasMany<Member, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }

    /** @return BelongsTo<BillableItem, $this> */
    public function adultBillableItem(): BelongsTo
    {
        return $this->belongsTo(BillableItem::class, 'adult_billable_item_id');
    }

    /** @return BelongsTo<BillableItem, $this> */
    public function kidsBillableItem(): BelongsTo
    {
        return $this->belongsTo(BillableItem::class, 'kids_billable_item_id');
    }
}
