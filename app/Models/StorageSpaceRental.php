<?php

declare(strict_types=1);

namespace App\Models;

use App\Observers\StorageSpaceRentalObserver;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * @property CarbonInterface $start_date
 * @property ?CarbonInterface $end_date
 */
#[Fillable(['storage_space_id', 'member_id', 'start_date', 'end_date', 'billable_item_instance_id'])]
#[ObservedBy([StorageSpaceRentalObserver::class])]
final class StorageSpaceRental extends Model
{
    use HasFactory;

    /** @return BelongsTo<StorageSpace, $this> */
    public function storageSpace(): BelongsTo
    {
        return $this->belongsTo(StorageSpace::class);
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** @return BelongsTo<BillableItemInstance, $this> */
    public function billableItemInstance(): BelongsTo
    {
        return $this->belongsTo(BillableItemInstance::class);
    }

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }
}
