<?php

declare(strict_types=1);

namespace App\Domain\Members;

use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface MemberUserRepository
{
    /** Create a login account for the member and link it (no email is sent here). */
    public function provision(MemberId $memberId): void;
}
