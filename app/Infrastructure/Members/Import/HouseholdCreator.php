<?php

declare(strict_types=1);

namespace App\Infrastructure\Members\Import;

use App\Models\Household;
use App\Models\Member;
use Illuminate\Support\Collection;

final readonly class HouseholdCreator
{
    /** @param list<CreatedMember> $continuingMembers */
    public function create(array $continuingMembers): int
    {
        $households = collect($continuingMembers)
            ->filter(static fn (CreatedMember $member): bool => !$member->row->isLeaver())
            ->groupBy(static fn (CreatedMember $member): string => $member->row->addressKey())
            ->filter(static fn (Collection $members): bool => $members->count() >= 2);

        $households->each(static function (Collection $members): void {
            $household = Household::query()->create([]);
            $memberIds = $members->map(static fn (CreatedMember $member): int => $member->memberId->value)->all();

            Member::query()->whereKey($memberIds)->update(['household_id' => $household->id]);
        });

        return $households->count();
    }
}
