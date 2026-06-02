<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Members\Gender;
use App\Models\Pivots\ActivityMember;
use App\Observers\MemberObserver;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Guarded('id', 'updated_at', 'created_at')]
#[ObservedBy([MemberObserver::class])]
final class Member extends Model
{
    use HasFactory;
    use SoftDeletes;

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

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<Activity, $this, ActivityMember> */
    public function activities(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this
            ->belongsToMany(Activity::class)
            ->using(ActivityMember::class)
            ->withPivot('billable_item_instance_id')
            ->withTimestamps();
    }

    /** @return HasMany<MemberObject, $this> */
    public function memberObjects(): HasMany
    {
        return $this->hasMany(MemberObject::class);
    }

    /** @return BelongsTo<Household, $this> */
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    /**
     * Get other members in the same household. If the member has no household
     * this returns an empty relation.
     *
     * @return HasMany<Member, $this>
     */
    public function householdMembers(): HasMany
    {
        // If no household set, return an empty relationship by constraining to false
        if ($this->household_id === null) {
            return $this->hasMany(self::class, 'household_id', 'id')->whereRaw('1 = 0');
        }

        // Return other members in the same household (excluding self)
        return $this->hasMany(self::class, 'household_id', 'household_id')
            ->where('id', '!=', $this->id);
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

    /** @return Attribute<int, never> */
    protected function age(): Attribute
    {
        return Attribute::get(static function (mixed $value, array $attributes) {
            return (int) floor(new Carbon($attributes['birthdate'])->diffInYears());
        });
    }

    /** @return Attribute<non-falsy-string, never> */
    protected function address(): Attribute
    {
        return Attribute::get(static function (mixed $value, array $attributes): string {
            $lineOne = sprintf('%s %s', $attributes['address_street'], $attributes['address_housenumber']);

            if (!empty($attributes['address_housenumber_addition'])) {
                $lineOne .= $attributes['address_housenumber_addition'];
            }

            $lineTwo = sprintf('%s, %s', $attributes['address_postalcode'], $attributes['address_city']);

            return sprintf("%s\n%s", $lineOne, $lineTwo);
        });
    }
}
