<?php

declare(strict_types=1);

namespace App\Domain\Members;

final readonly class Member
{
    public function __construct(
        public MemberId $id,
        public MembershipId $membershipId,
        public bool $isVolunteer,
    ) {
    }
}
