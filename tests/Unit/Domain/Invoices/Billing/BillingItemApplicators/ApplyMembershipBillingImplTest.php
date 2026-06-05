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

    public function test_it_applies_adult_billable_item_for_adult_member(): void
    {
        $memberId = MemberId::create(1);
        $membershipId = MembershipId::create(2);
        $adultBillableItemId1 = BillableItemId::create(10);
        $kidsBillableItemId1 = BillableItemId::create(11);
        $adultBillableItemId2 = BillableItemId::create(20);
        $kidsBillableItemId2 = BillableItemId::create(21);

        $member = new Member(
            id: $memberId,
            membershipId: $membershipId,
            isVolunteer: false,
            householdId: null,
            age: 18,
        );

        $membership1 = new Membership(MembershipId::create(1), $adultBillableItemId1, $kidsBillableItemId1);
        $membership2 = new Membership($membershipId, $adultBillableItemId2, $kidsBillableItemId2);
        $allMemberships = new MembershipList([$membership1, $membership2]);

        $this->memberRepo->expectsGetById($memberId, $member);
        $this->membershipRepo->expectsAll($allMemberships);
        $this->billableRepo->expectsRemove(
            $memberId,
            new BillableItemIdList([$adultBillableItemId1, $kidsBillableItemId1, $adultBillableItemId2, $kidsBillableItemId2])
        );
        $this->membershipRepo->expectsGetById($membershipId, $membership2);
        $this->billableRepo->expectsAdd($memberId, $adultBillableItemId2, null, BillableItemInstanceId::create(12));

        $this->subject->apply($memberId, $membershipId);
    }

    public function test_it_applies_kids_billable_item_for_youngster_member(): void
    {
        $memberId = MemberId::create(1);
        $membershipId = MembershipId::create(2);
        $adultBillableItemId1 = BillableItemId::create(10);
        $kidsBillableItemId1 = BillableItemId::create(11);
        $adultBillableItemId2 = BillableItemId::create(20);
        $kidsBillableItemId2 = BillableItemId::create(21);

        $member = new Member(
            id: $memberId,
            membershipId: $membershipId,
            isVolunteer: false,
            householdId: null,
            age: 14,
        );

        $membership1 = new Membership(MembershipId::create(1), $adultBillableItemId1, $kidsBillableItemId1);
        $membership2 = new Membership($membershipId, $adultBillableItemId2, $kidsBillableItemId2);
        $allMemberships = new MembershipList([$membership1, $membership2]);

        $this->memberRepo->expectsGetById($memberId, $member);
        $this->membershipRepo->expectsAll($allMemberships);
        $this->billableRepo->expectsRemove(
            $memberId,
            new BillableItemIdList([$adultBillableItemId1, $kidsBillableItemId1, $adultBillableItemId2, $kidsBillableItemId2])
        );
        $this->membershipRepo->expectsGetById($membershipId, $membership2);
        $this->billableRepo->expectsAdd($memberId, $kidsBillableItemId2, null, BillableItemInstanceId::create(12));

        $this->subject->apply($memberId, $membershipId);
    }
}
