<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices;

use App\Domain\Invoices\Billing\ApplyMembershipBilling;
use App\Domain\Invoices\Billing\BillableItemId;
use App\Domain\Invoices\Billing\BillableItemIdList;
use App\Domain\Members\Member;
use App\Domain\Members\MemberId;
use App\Domain\Members\Membership;
use App\Domain\Members\MembershipId;
use App\Domain\Members\MembershipList;
use Tests\Unit\Domain\Members\MemberRepositoryExpectation;
use Tests\Unit\Domain\Members\MembershipRepositoryExpectation;
use Tests\UnitTestCase;

final class ApplyMembershipBillingTest extends UnitTestCase
{
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
        );

        $membership1 = new Membership($membershipId1, $billableItemId1);
        $membership2 = new Membership($membershipId2, $billableItemId2);
        $allMemberships = new MembershipList([$membership1, $membership2]);

        $newMembership = $membership2;

        $memberRepo = MemberRepositoryExpectation::create();
        $membershipRepo = MembershipRepositoryExpectation::create();
        $billableRepo = BillableItemRepositoryExpectation::create();

        $memberRepo->expectsGetById($memberId, $member);
        $membershipRepo->expectsAll($allMemberships);
        $billableRepo->expectsRemoveInstances(
            $memberId,
            new BillableItemIdList([$billableItemId1, $billableItemId2])
        );
        $membershipRepo->expectsGetById($membershipId2, $newMembership);
        $billableRepo->expectsAddInstance($memberId, $billableItemId2);

        $subject = new ApplyMembershipBilling($memberRepo->mock, $membershipRepo->mock, $billableRepo->mock);

        ($subject)($memberId, $membershipId2);
    }
}
