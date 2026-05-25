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
        $member = new Member(id: $memberId, membershipId: MembershipId::create(2)); // already updated

        $membership1 = new Membership(MembershipId::create(1), BillableItemId::create(10));
        $membership2 = new Membership(MembershipId::create(2), BillableItemId::create(20));
        $allMemberships = new MembershipList([$membership1, $membership2]);

        $newMembership = $membership2;

        $memberRepo = MemberRepositoryExpectation::create();
        $membershipRepo = MembershipRepositoryExpectation::create();
        $billableRepo = BillableItemRepositoryExpectation::create();

        $memberRepo->expectsGetById($memberId, $member);
        $membershipRepo->expectsAll($allMemberships);
        $billableRepo->expectsRemoveInstances(
            $memberId,
            new BillableItemIdList([BillableItemId::create(10), BillableItemId::create(20)])
        );
        $membershipRepo->expectsGetById(MembershipId::create(2), $newMembership);
        $billableRepo->expectsAddInstance($memberId, BillableItemId::create(20));

        $sut = new ApplyMembershipBilling($memberRepo->mock, $membershipRepo->mock, $billableRepo->mock);

        ($sut)($memberId, MembershipId::create(2));
    }
}
