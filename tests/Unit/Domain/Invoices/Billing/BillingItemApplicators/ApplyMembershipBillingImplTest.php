<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices\Billing\BillingItemApplicators;

use App\Domain\Invoices\Billing\BillableItemId;
use App\Domain\Invoices\Billing\BillableItemIdList;
use App\Domain\Invoices\Billing\BillableItemInstanceId;
use App\Domain\Invoices\Billing\BillingItemApplicators\ApplyMembershipBillingImpl;
use App\Domain\Members\Member;
use App\Domain\Members\MemberId;
use App\Domain\Members\Membership;
use App\Domain\Members\MembershipId;
use App\Domain\Members\MembershipList;
use Tests\Unit\Domain\Invoices\BillableItemRepositoryExpectation;
use Tests\Unit\Domain\Members\MemberRepositoryExpectation;
use Tests\Unit\Domain\Members\MembershipRepositoryExpectation;
use Tests\UnitTestCase;

final class ApplyMembershipBillingImplTest extends UnitTestCase
{
    private MemberRepositoryExpectation $memberRepo;

    private MembershipRepositoryExpectation $membershipRepo;

    private BillableItemRepositoryExpectation $billableRepo;

    private ApplyMembershipBillingImpl $subject;

    protected function setup(): void
    {
        parent::setup();

        $this->memberRepo = MemberRepositoryExpectation::create();
        $this->membershipRepo = MembershipRepositoryExpectation::create();
        $this->billableRepo = BillableItemRepositoryExpectation::create();

        $this->subject = new ApplyMembershipBillingImpl(
            $this->memberRepo->mock,
            $this->membershipRepo->mock,
            $this->billableRepo->mock,
        );
    }

    public function test_it_removes_old_membership_billable_item_and_adds_new_one(): void
    {
        $memberId = MemberId::create(1);
        $membershipId1 = MembershipId::create(1);
        $membershipId2 = MembershipId::create(2);
        $billableItemId1 = BillableItemId::create(10);
        $billableItemId2 = BillableItemId::create(20);

        $member = new Member(
            id: $memberId,
            membershipId: $membershipId2,
            isVolunteer: false,
            householdId: null,
            age: 19,
        );

        $membership1 = new Membership($membershipId1, $billableItemId1);
        $membership2 = new Membership($membershipId2, $billableItemId2);
        $allMemberships = new MembershipList([$membership1, $membership2]);

        $newMembership = $membership2;

        $this->memberRepo->expectsGetById($memberId, $member);
        $this->membershipRepo->expectsAll($allMemberships);
        $this->billableRepo->expectsRemove(
            $memberId,
            new BillableItemIdList([$billableItemId1, $billableItemId2])
        );
        $this->membershipRepo->expectsGetById($membershipId2, $newMembership);
        $this->billableRepo->expectsAdd($memberId, $billableItemId2, null, BillableItemInstanceId::create(12));

        $this->subject->apply($memberId, $membershipId2);
    }
}
