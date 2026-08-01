<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

final class MemberObjectType extends Model
{
    use HasFactory;

    #[Override]
    protected $fillable = ['name', 'billable_item_id'];

    /** @return BelongsTo<BillableItem, $this> */
    public function billableItem(): BelongsTo
    {
        return $this->belongsTo(BillableItem::class);
    }
}
