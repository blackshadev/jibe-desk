<?php

declare(strict_types=1);

namespace App\Domain\Members\Events;

use App\Domain\Members\MemberId;

final readonly class MemberAccepted
{
    public function __construct(
        public MemberId $memberId,
    ) {}
}
