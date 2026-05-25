<?php

declare(strict_types=1);

namespace App\Domain\Members;

use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface MemberRepository
{
    public function getById(MemberId $memberId): Member;
}
