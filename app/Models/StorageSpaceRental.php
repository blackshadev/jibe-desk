<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['storage_space_id', 'member_id', 'start_date', 'end_date'])]
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

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }
}
