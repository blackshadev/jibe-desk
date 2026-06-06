<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Guarded('id', 'updated_at', 'created_at')]
final class StorageSpace extends Model
{
    use HasFactory;

    /** @return HasMany<StorageSpaceRental, $this> */
    public function rentals(): HasMany
    {
        return $this->hasMany(StorageSpaceRental::class);
    }
}
