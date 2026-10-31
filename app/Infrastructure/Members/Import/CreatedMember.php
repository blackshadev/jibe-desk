<?php

declare(strict_types=1);

namespace App\Infrastructure\Members\Import;

use App\Domain\Members\MemberId;
use App\Domain\Members\MembershipId;

final readonly class CreatedMember
{
    public function __construct(
        public MemberId $memberId,
        public MembershipId $membershipId,
        public MappedMemberRow $row,
    ) {}
}
