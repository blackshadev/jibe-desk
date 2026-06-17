<?php

declare(strict_types=1);

namespace App\Domain\Members;

use App\Domain\Members\Dto\NewMember;
use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface MemberRepository
{
    public function getById(MemberId $memberId): Member;

    public function newMember(NewMember $newMember): MemberId;

    public function getByEmail(string $email): ?MemberId;
}
