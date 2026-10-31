<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Members\Gender;
use App\Domain\Members\MemberNameFormatter;
use App\Domain\Members\PaymentMethod;
use App\Domain\Registration\FormData;
use App\Models\Pivots\ActivityMember;
use App\Observers\MemberObserver;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Override;
use RuntimeException;

/**
 * @phpstan-import-type FormDataArray from FormData
 *
 * @property PaymentMethod $payment_method
 * @property null|DateTimeInterface $stopped_at
 * @property null|DateTimeInterface $accepted_at
 * @property FormDataArray|null $registration_data
 */
#[Guarded('id', 'updated_at', 'created_at')]
#[ObservedBy([MemberObserver::class])]
final class Member extends Model
{
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * The member ids visible to this member's login: self + household members.
     *
     * @return list<int>
     */
    public function visibleMemberIds(): array
    {
        if ($this->household_id === null) {
            return [$this->id];
        }

        return self::query()
            ->where('household_id', $this->household_id)
            ->pluck('id')
            ->map(static fn (int $id): int => $id)
            ->all();
    }

    /** @return HasMany<PurchaseOrder, $this> */
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
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

    /** @return BelongsToMany<Activity, $this, ActivityMember> */
    public function activities(): BelongsToMany
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

    /** @return HasMany<StorageSpaceRental, $this> */
    public function storageSpaceRentals(): HasMany
    {
        return $this->hasMany(StorageSpaceRental::class);
    }

    /** @return HasOne<PaymentInformation, $this> */
    public function paymentInformation(): HasOne
    {
        return $this->hasOne(PaymentInformation::class)->whereNull('revoked_at');
    }

    /**
     * @return HasOne<PaymentInformation, $this>
     */
    public function currentPaymentInformation(): HasOne
    {
        return $this->hasOne(PaymentInformation::class)->where(
            'payment_information.id',
            DB::raw('(select current_mandate.id
                    from payment_information as current_mandate
                    where current_mandate.member_id = payment_information.member_id
                    order by current_mandate.revoked_at is null desc, current_mandate.id desc
                    limit 1)'),
        );
    }

    /** @return HasMany<PaymentInformation, $this> */
    public function paymentInformations(): HasMany
    {
        return $this->hasMany(PaymentInformation::class)->orderByDesc('id');
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

    /** @return HasMany<OutgoingEmail, $this> */
    public function outgoingEmails(): HasMany
    {
        return $this->hasMany(OutgoingEmail::class);
    }

    public function stop(): void
    {
        if ($this->stopped_at !== null) {
            throw new RuntimeException('Already stopped');
        }

        $this->stopped_at = now();
        $this->save();
    }

    public function isActive(): bool
    {
        return $this->accepted_at !== null && !$this->isStopInEffect();
    }

    public function isScheduled(): bool
    {
        return $this->isActive() && $this->stopped_at !== null;
    }

    private function isStopInEffect(): bool
    {
        if ($this->stopped_at === null) {
            return false;
        }

        return !CarbonImmutable::instance($this->stopped_at)->isFuture();
    }

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'birthdate' => 'date',
            'is_volunteer' => 'boolean',
            'gender' => Gender::class,
            'payment_method' => PaymentMethod::class,
            'registration_data' => 'array',
            'stopped_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query
            ->whereNotNull('accepted_at')
            ->where(static fn (Builder $query) => $query
                ->whereNull('stopped_at')
                ->orWhereFuture('stopped_at'));
    }

    #[Scope]
    protected function scheduled(Builder $query): Builder
    {
        return $query->whereNotNull('accepted_at')->whereFuture('stopped_at');
    }

    #[Scope]
    protected function stopped(Builder $query): Builder
    {
        return $query->whereNowOrPast('stopped_at');
    }

    #[Scope]
    protected function pending(Builder $query): Builder
    {
        return $query->whereNull('accepted_at');
    }

    #[Scope]
    protected function accepted(Builder $query): Builder
    {
        return $query->whereNotNull('accepted_at');
    }

    public function isPending(): bool
    {
        return $this->accepted_at === null;
    }

    /** @return Attribute<non-falsy-string, never> */
    protected function name(): Attribute
    {
        return Attribute::get(static fn (mixed $_value, array $attributes) => MemberNameFormatter::displayName(
            $attributes['first_name'],
            $attributes['infix_name'],
            $attributes['last_name'],
        ));
    }

    /** @return Attribute<int, never> */
    protected function age(): Attribute
    {
        return Attribute::get(static fn (mixed $_value, array $attributes) => (int) floor(new Carbon($attributes['birthdate'])->diffInYears()));
    }

    /** @return Attribute<non-falsy-string, never> */
    protected function address(): Attribute
    {
        return Attribute::get(static function (mixed $_value, array $attributes): string {
            $lineOne = sprintf('%s %s', $attributes['address_street'], $attributes['address_housenumber']);

            if ($attributes['address_housenumber_addition'] !== null) {
                $lineOne .= $attributes['address_housenumber_addition'];
            }

            $lineTwo = sprintf('%s, %s', $attributes['address_postalcode'], $attributes['address_city']);

            return sprintf("%s\n%s", $lineOne, $lineTwo);
        });
    }
}
