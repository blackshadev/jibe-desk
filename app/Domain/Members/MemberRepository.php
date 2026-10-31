<?php

declare(strict_types=1);

namespace App\Domain\Members;

use App\Domain\Members\Dto\NewMember;
use DateTimeInterface;
use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface MemberRepository
{
    public function getById(MemberId $memberId): Member;

    public function newMember(NewMember $newMember): MemberId;

    public function getByEmail(string $email): ?MemberId;

    public function accept(MemberId $memberId): void;

    public function scheduleStop(MemberId $memberId, DateTimeInterface $stoppedAt): void;

    public function cancelStop(MemberId $memberId): void;
}
