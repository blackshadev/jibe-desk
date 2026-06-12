<?php

declare(strict_types=1);

namespace App\Domain\Members\Dto;

use App\Domain\Members\MembershipId;

final readonly class NewMemberMembershipInformation
{
    public function __construct(
        public MembershipId $membershipId,
    ) {
    }
}
