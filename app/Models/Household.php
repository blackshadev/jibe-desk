<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Member;

final class Household extends Model
{
    use HasFactory;

    /** @return HasMany<Member, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }

    public function getMemberNamesAttribute(): string
    {
        return $this->members->map(fn (Member $m) => $m->name)->join(', ');
    }
}
