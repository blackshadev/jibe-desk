<?php

declare(strict_types=1);

namespace App\Events;

use App\Domain\Members\MemberId;
use App\Domain\Members\MembershipId;

final readonly class MemberMembershipChanged
{
    public function __construct(
        public MemberId $memberId,
        public MembershipId $newMembershipId,
    ) {
    }
}
