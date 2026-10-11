<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Members\Listeners;

use App\Domain\Members\Events\MemberAccepted;
use App\Domain\Members\Listeners\ApplyMemberAcceptedBilling;
use App\Domain\Members\Member;
use App\Domain\Members\MemberId;
use App\Domain\Members\MembershipId;
use DateTimeImmutable;
use Override;
use Tests\Unit\Domain\Invoices\Billing\BillingItemApplicators\ApplyMembershipBillingExpectation;
use Tests\Unit\Domain\Invoices\Billing\BillingItemApplicators\ApplyMemberVolunteerBillingExpectation;
use Tests\Unit\Domain\Invoices\Billing\BillingItemApplicators\ApplySameHouseholdBillingExpectation;
use Tests\Unit\Domain\Members\MemberRepositoryExpectation;
use Tests\UnitTestCase;

final class ApplyMemberAcceptedBillingTest extends UnitTestCase
{
    private MemberRepositoryExpectation $memberRepo;

    private ApplyMembershipBillingExpectation $membershipBilling;

    private ApplyMemberVolunteerBillingExpectation $volunteerBilling;

    private ApplySameHouseholdBillingExpectation $householdBilling;

    private ApplyMemberAcceptedBilling $subject;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->memberRepo = MemberRepositoryExpectation::create();
        $this->membershipBilling = ApplyMembershipBillingExpectation::create();
        $this->volunteerBilling = ApplyMemberVolunteerBillingExpectation::create();
        $this->householdBilling = ApplySameHouseholdBillingExpectation::create();

        $this->subject = new ApplyMemberAcceptedBilling(
            $this->memberRepo->mock,
            $this->membershipBilling->mock,
            $this->volunteerBilling->mock,
            $this->householdBilling->mock,
        );
    }

    public function test_it_applies_all_billing_on_member_accepted(): void
    {
        $memberId = MemberId::create(1);
        $membershipId = MembershipId::create(2);

        $this->memberRepo
            ->mock
            ->expects('getById')
            ->with($memberId)
            ->andReturn(new Member(
                id: $memberId,
                membershipId: $membershipId,
                isVolunteer: false,
                householdId: null,
                age: 30,
                name: 'Jan de Vries',
                email: 'jan@example.com',
                acceptedAt: new DateTimeImmutable('2024-01-01'),
            ));

        $this->membershipBilling->expectsApply($memberId, $membershipId);
        $this->volunteerBilling->expectsApply($memberId);
        $this->householdBilling->expectsApply($memberId);

        $this->subject->handle(new MemberAccepted($memberId));
    }
}
