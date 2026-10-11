<?php

declare(strict_types=1);

namespace App\Domain\Members\Events;

use App\Domain\Members\MemberId;

final readonly class MemberAccountProvisioned
{
    public function __construct(
        public MemberId $memberId,
        public string $memberName,
        public string $memberEmail,
    ) {}
}
