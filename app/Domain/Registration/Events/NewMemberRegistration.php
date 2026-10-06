<?php

declare(strict_types=1);

namespace App\Domain\Registration\Events;

use App\Domain\Members\MemberId;
use App\Domain\Registration\MembershipData;
use App\Domain\Registration\RegistrationSource;

final readonly class NewMemberRegistration
{
    public function __construct(
        public MemberId $memberId,
        public string $memberName,
        public string $memberEmail,
        public MembershipData $membershipData,
        public RegistrationSource $source,
    ) {}
}
