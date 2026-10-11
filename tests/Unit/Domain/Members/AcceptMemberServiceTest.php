<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Members;

use App\Domain\Members\AcceptMemberService;
use App\Domain\Members\Events\MemberAccepted;
use App\Domain\Members\Events\MemberAccountProvisioned;
use App\Domain\Members\Member;
use App\Domain\Members\MemberId;
use App\Domain\Members\MembershipId;
use DateTimeImmutable;
use Mockery;
use Override;
use Tests\Unit\Laravel\EventDispatcherExpectation;
use Tests\UnitTestCase;

final class AcceptMemberServiceTest extends UnitTestCase
{
    private MemberRepositoryExpectation $memberRepo;

    private MemberUserRepositoryExpectation $memberUserRepo;

    private EventDispatcherExpectation $eventDispatcher;

    private AcceptMemberService $subject;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->memberRepo = MemberRepositoryExpectation::create();
        $this->memberUserRepo = MemberUserRepositoryExpectation::create();
        $this->eventDispatcher = EventDispatcherExpectation::create();

        $this->subject = new AcceptMemberService(
            $this->memberRepo->mock,
            $this->memberUserRepo->mock,
            $this->eventDispatcher->mock,
        );
    }

    public function test_it_accepts_a_pending_member(): void
    {
        $memberId = MemberId::create(1);

        $this->memberRepo
            ->mock
            ->expects('getById')
            ->with($memberId)
            ->andReturn(new Member(
                id: $memberId,
                membershipId: MembershipId::create(2),
                isVolunteer: false,
                householdId: null,
                age: 30,
                name: 'Jan de Vries',
                email: 'jan@example.com',
                acceptedAt: null,
            ));

        $this->memberRepo->expectsAccept($memberId);
        $this->memberUserRepo->expectsProvision($memberId);

        $this->eventDispatcher
            ->mock
            ->expects('dispatch')
            ->with(Mockery::type(MemberAccepted::class))
            ->ordered()
            ->andReturnNull();

        $this->eventDispatcher
            ->mock
            ->expects('dispatch')
            ->with(Mockery::on(
                static fn (MemberAccountProvisioned $event): bool => (
                    $event->memberId->value === $memberId->value
                    && $event->memberName === 'Jan de Vries'
                    && $event->memberEmail === 'jan@example.com'
                ),
            ))
            ->ordered()
            ->andReturnNull();

        $this->subject->accept($memberId);
    }

    public function test_it_is_a_no_op_for_an_accepted_member(): void
    {
        $memberId = MemberId::create(1);

        $this->memberRepo
            ->mock
            ->expects('getById')
            ->with($memberId)
            ->andReturn(new Member(
                id: $memberId,
                membershipId: MembershipId::create(2),
                isVolunteer: false,
                householdId: null,
                age: 30,
                name: 'Jan de Vries',
                email: 'jan@example.com',
                acceptedAt: new DateTimeImmutable('2024-01-01'),
            ));

        $this->memberRepo->expectsAcceptNever();
        $this->memberUserRepo->mock->expects('provision')->never();
        $this->eventDispatcher->expectsNotToDispatch();

        $this->subject->accept($memberId);
    }
}
