<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
}
