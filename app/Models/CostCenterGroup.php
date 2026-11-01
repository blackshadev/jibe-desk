<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Bookkeeping\CostCenters\CostCenterGroupCycleException;
use App\Observers\CostCenterGroupObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Override;

/**
 * @property-read string $label
 */
#[Fillable(['number', 'external_number', 'name', 'parent_id'])]
#[ObservedBy([CostCenterGroupObserver::class])]
final class CostCenterGroup extends Model
{
    use HasFactory;

    /** @return BelongsTo<self, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<self, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** @return HasMany<CostCenter, $this> */
    public function costCenters(): HasMany
    {
        return $this->hasMany(CostCenter::class);
    }

    /**
     * Qualified numbers form the tree path, so a group's subtree shares its number as prefix.
     *
     * @return list<int>
     */
    public function selfAndDescendantIds(): array
    {
        if (!$this->exists) {
            return [];
        }

        return self::query()
            ->where('number', $this->number)
            ->orWhere('number', 'like', $this->number . '.%')
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }

    /** @return Attribute<string, never> */
    protected function label(): Attribute
    {
        return Attribute::make(
            get: static fn (mixed $value, array $attributes): string => sprintf(
                '%s %s',
                $attributes['number'],
                $attributes['name'],
            ),
        );
    }

    #[Override]
    protected static function booted(): void
    {
        self::saving(static function (self $group): void {
            if (!$group->exists || $group->parent_id === null) {
                return;
            }

            if (!in_array((int) $group->parent_id, $group->selfAndDescendantIds(), true)) {
                return;
            }

            throw CostCenterGroupCycleException::forGroup($group->number);
        });
    }
}
