<?php

declare(strict_types=1);

namespace App\Domain\Members;

use App\Domain\Members\Events\MemberStopScheduled;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Events\Dispatcher;
use Psr\Clock\ClockInterface;

final readonly class MemberStopService
{
    public function __construct(
        private MemberRepository $memberRepository,
        private Dispatcher $eventDispatcher,
        private ClockInterface $clock,
    ) {}

    public function schedule(MemberId $memberId, DateTimeInterface $stopDate): void
    {
        $member = $this->memberRepository->getById($memberId);
        $now = $this->clock->now();
        $immediate = CarbonImmutable::instance($stopDate)->isSameDay($now);
        $stoppedAt = $immediate ? $now : CarbonImmutable::instance($stopDate)->endOfDay();

        $this->memberRepository->scheduleStop($memberId, $stoppedAt);

        $this->eventDispatcher->dispatch(new MemberStopScheduled(
            memberId: $memberId,
            memberName: $member->name,
            memberEmail: $member->email,
            stoppedAt: $stoppedAt,
            immediate: $immediate,
        ));
    }

    public function cancel(MemberId $memberId): void
    {
        $this->memberRepository->cancelStop($memberId);
    }
}
