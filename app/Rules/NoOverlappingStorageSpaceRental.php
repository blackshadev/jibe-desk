<?php

declare(strict_types=1);

namespace App\Rules;

use App\Domain\Members\MemberNameFormatter;
use App\Models\StorageSpaceRental;
use Closure;
use DateTimeInterface;
use Illuminate\Contracts\Validation\ValidationRule;
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
        $member = StorageSpaceRental::query()
            ->overlapping(
                storageSpaceId: $this->storageSpaceId,
                startDate: $this->startDate,
                endDate: $this->endDate,
                excludeRentalIds: $this->excludeRentalIds,
            )
            ->joinRelationship('member')
            ->first(['members.first_name', 'members.last_name', 'members.infix_name']);

        if ($member === null) {
            return;
        }

        $fullName = MemberNameFormatter::displayName($member['first_name'], $member['infix_name'], $member['last_name']);

        $fail(__('validation.no_overlapping_storage_space_rental', ['member' => $fullName]));
    }
}
