<?php

declare(strict_types=1);

namespace App\Domain\Members\Events;

use App\Domain\Members\MemberId;
use DateTimeInterface;

final readonly class MemberStopScheduled
{
    public function __construct(
        public MemberId $memberId,
        public string $memberName,
        public string $memberEmail,
        public DateTimeInterface $stoppedAt,
        public bool $isImmediate,
    ) {}
}
