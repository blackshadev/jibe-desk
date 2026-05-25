<?php

declare(strict_types=1);

namespace App\Events;

use App\Domain\Members\MemberId;

final readonly class MemberVolunteerChanged
{
    public function __construct(
        public MemberId $memberId,
    ) {
    }
}
