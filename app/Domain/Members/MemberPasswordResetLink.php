<?php

declare(strict_types=1);

namespace App\Domain\Members;

use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface MemberPasswordResetLink
{
    /**
     * Generate a one-time password reset URL for the account with the given
     * member ID
     */
    public function generate(MemberId $memberId): string;
}
