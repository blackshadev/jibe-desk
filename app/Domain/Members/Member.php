<?php

declare(strict_types=1);

namespace App\Domain\Members;

use DateTimeInterface;

final readonly class Member
{
    public function __construct(
        public MemberId $id,
        public MembershipId $membershipId,
        public bool $isVolunteer,
        public ?HouseholdId $householdId,
        public int $age,
        public string $name,
        public string $email,
        public ?DateTimeInterface $acceptedAt,
    ) {}

    public function isInHousehold(): bool
    {
        return $this->householdId !== null;
    }

    public function isYoungster(): bool
    {
        return $this->age < 18;
    }

    public function isPending(): bool
    {
        return $this->acceptedAt === null;
    }
}
