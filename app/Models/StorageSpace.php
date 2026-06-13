<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Guarded('id', 'updated_at', 'created_at')]
final class StorageSpace extends Model
{
    use HasFactory;

    /** @return BelongsTo<StorageSpaceLocation, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(StorageSpaceLocation::class, 'storage_space_location_id');
    }

    /** @return HasMany<StorageSpaceRental, $this> */
    public function rentals(): HasMany
    {
        return $this->hasMany(StorageSpaceRental::class);
    }

    /** @return HasOne<StorageSpaceRental, $this> */
    public function currentRental(): HasOne
    {
        return $this
            ->hasOne(StorageSpaceRental::class)
            ->whereNowOrPast('start_date')
            ->where(static fn (Builder $query) => $query->whereNull('end_date')->orWhereFuture('end_date'))
            ->oldest('start_date');
    }

    #[Scope]
    protected function _available(Builder $query): Builder
    {
        return $query->whereDoesntHave('currentRental');
    }

    #[Scope]
    protected function _unavailable(Builder $query): Builder
    {
        return $query->whereHas('currentRental');
    }
}
