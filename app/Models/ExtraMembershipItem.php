<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Members\ExtraMembershipItemCode;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property ExtraMembershipItemCode $code */
#[Fillable(['billable_item_id', 'code'])]
final class ExtraMembershipItem extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'code' => ExtraMembershipItemCode::class,
        ];
    }

    /** @return BelongsTo<BillableItem, $this> */
    public function billableItem(): BelongsTo
    {
        return $this->belongsTo(BillableItem::class);
    }
}
