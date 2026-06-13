<?php

declare(strict_types=1);

namespace App\Rules;

use App\Domain\Members\MemberNameFormatter;
use App\Models\StorageSpaceRental;
use Carbon\CarbonImmutable;
use Closure;
use DateTimeInterface;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Override;

final readonly class NoOverlappingStorageSpaceRental implements ValidationRule
{
    /**
     * @param int[] $excludeRentalIds
     */
    public function __construct(
        private int $storageSpaceId,
        private ?DateTimeInterface $startDate,
        private ?DateTimeInterface $endDate,
        private array $excludeRentalIds = [],
    ) {}

    #[Override]
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $effectiveEndDate = $this->endDate ?? CarbonImmutable::create('9999-12-31 23:59:59');
        $effectiveStartDate = $this->startDate ?? CarbonImmutable::startOfTime();

        $query = StorageSpaceRental::query()
            ->joinRelationship('member')
            ->where('storage_space_rentals.storage_space_id', $this->storageSpaceId)
            ->where('storage_space_rentals.start_date', '<', $effectiveEndDate)
            ->where(static function (Builder $q) use ($effectiveStartDate): void {
                $q->whereNull('storage_space_rentals.end_date')
                    ->orWhere('storage_space_rentals.end_date', '>', $effectiveStartDate);
            });

        if ($this->excludeRentalIds !== []) {
            $query->whereNotIn('storage_space_rentals.id', $this->excludeRentalIds);
        }

        $member = $query->get(['members.first_name', 'members.last_name', 'members.infix_name'])->first();

        if ($member !== null) {
            $fullName = MemberNameFormatter::displayName($member['first_name'], $member['infix_name'], $member['last_name']);

            $fail(__('validation.no_overlapping_storage_space_rental', ['member' => $fullName]));
        }
    }
}
