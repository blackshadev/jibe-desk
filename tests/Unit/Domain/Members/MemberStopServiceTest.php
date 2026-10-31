<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Members;

use App\Domain\Members\Events\MemberStopScheduled;
use App\Domain\Members\Member;
use App\Domain\Members\MemberId;
use App\Domain\Members\MembershipId;
use App\Domain\Members\MemberStopService;
use Carbon\CarbonImmutable;
use Override;
use Tests\Unit\Domain\Clock\ClockExpectation;
use Tests\Unit\Laravel\EventDispatcherExpectation;
use Tests\UnitTestCase;

final class MemberStopServiceTest extends UnitTestCase
{
    private MemberRepositoryExpectation $memberRepo;

    private EventDispatcherExpectation $eventDispatcher;

    private ClockExpectation $clock;

    private MemberStopService $subject;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->memberRepo = MemberRepositoryExpectation::create();
        $this->eventDispatcher = EventDispatcherExpectation::create();
        $this->clock = ClockExpectation::create();

        $this->subject = new MemberStopService(
            $this->memberRepo->mock,
            $this->eventDispatcher->mock,
            $this->clock->mock,
        );
    }

    public function test_it_schedules_a_future_stop_at_the_end_of_the_selected_day(): void
    {
        $memberId = MemberId::create(1);
        $now = CarbonImmutable::parse('2026-06-15 09:30:00');
        $stopDate = CarbonImmutable::parse('2026-12-31');
        $stoppedAt = CarbonImmutable::parse('2026-12-31')->endOfDay();
        $stopRequestedAt = CarbonImmutable::parse('2026-06-01');

        $this->memberRepo->expectsGetById($memberId, $this->member($memberId));
        $this->clock->expectsNow($now);
        $this->memberRepo->expectsScheduleStop($memberId, $stoppedAt, $stopRequestedAt);
        $this->eventDispatcher->expectsDispatch(new MemberStopScheduled(
            memberId: $memberId,
            memberName: 'Jan de Vries',
            memberEmail: 'jan@example.com',
            stoppedAt: $stoppedAt,
            isImmediate: false,
        ));

        $this->subject->schedule($memberId, $stopDate, $stopRequestedAt);
    }

    public function test_it_stops_immediately_when_the_selected_day_is_today(): void
    {
        $memberId = MemberId::create(1);
        $now = CarbonImmutable::parse('2026-06-15 09:30:00');
        $stopDate = CarbonImmutable::parse('2026-06-15');
        $stopRequestedAt = CarbonImmutable::parse('2026-06-01');

        $this->memberRepo->expectsGetById($memberId, $this->member($memberId));
        $this->clock->expectsNow($now);
        $this->memberRepo->expectsScheduleStop($memberId, $now, $stopRequestedAt);
        $this->eventDispatcher->expectsDispatch(new MemberStopScheduled(
            memberId: $memberId,
            memberName: 'Jan de Vries',
            memberEmail: 'jan@example.com',
            stoppedAt: $now,
            isImmediate: true,
        ));

        $this->subject->schedule($memberId, $stopDate, $stopRequestedAt);
    }

    public function test_it_cancels_the_stop_without_sending_an_event(): void
    {
        $memberId = MemberId::create(1);

        $this->memberRepo->expectsCancelStop($memberId);
        $this->eventDispatcher->expectsNotToDispatch();

        $this->subject->cancel($memberId);
    }

    private function member(MemberId $memberId): Member
    {
        return new Member(
            id: $memberId,
            membershipId: MembershipId::create(2),
            isVolunteer: false,
            householdId: null,
            age: 30,
            name: 'Jan de Vries',
            email: 'jan@example.com',
            acceptedAt: CarbonImmutable::parse('2024-01-01'),
        );
    }
}
