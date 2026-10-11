<?php

declare(strict_types=1);

namespace App\Domain\Members;

use App\Domain\Members\Events\MemberAccepted;
use App\Domain\Members\Events\MemberAccountProvisioned;
use Illuminate\Contracts\Events\Dispatcher;

final readonly class AcceptMemberService
{
    public function __construct(
        private MemberRepository $memberRepository,
        private MemberUserRepository $memberUserRepository,
        private Dispatcher $eventDispatcher,
    ) {}

    public function accept(MemberId $memberId): void
    {
        $member = $this->memberRepository->getById($memberId);

        if (!$member->isPending()) {
            return;
        }

        $this->memberRepository->accept($memberId);

        $this->eventDispatcher->dispatch(new MemberAccepted($memberId));

        $this->memberUserRepository->provision($memberId);

        $this->eventDispatcher->dispatch(new MemberAccountProvisioned(
            memberId: $memberId,
            memberName: $member->name,
            memberEmail: $member->email,
        ));
    }
}
