<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Members\Gender;
use App\Observers\MemberObserver;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Guarded('id', 'updated_at', 'created_at')]
#[ObservedBy([MemberObserver::class])]
final class Member extends Model
{
    use HasFactory;

    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /** @return BelongsTo<Membership, $this> */
    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }

    /** @return HasMany<BillableItemInstance, $this> */
    public function billableItemInstances(): HasMany
    {
        return $this->hasMany(BillableItemInstance::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'birthdate' => 'date',
            'is_volunteer' => 'boolean',
            'gender' => Gender::class,
        ];
    }

    /** @return Attribute<non-falsy-string, never> */
    protected function name(): Attribute
    {
        return Attribute::get(static function (mixed $value, array $attributes) {
            $firstName = empty($attributes['infix_name']) ? $attributes['first_name'] : "{$attributes['first_name']} {$attributes['infix_name']}";

            return sprintf('%s, %s', $attributes['last_name'], $firstName);
        });
    }
}
