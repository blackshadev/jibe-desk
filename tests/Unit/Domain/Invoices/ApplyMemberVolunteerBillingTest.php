<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices;

use App\Domain\Invoices\Billing\ApplyMemberVolunteerBilling;
use App\Domain\Invoices\Billing\BillableItemId;
use App\Domain\Invoices\Billing\BillableItemIdList;
use App\Domain\Members\ExtraMembershipItemCode;
use App\Domain\Members\Member;
use App\Domain\Members\MemberId;
use App\Domain\Members\MembershipId;
use Tests\Unit\Domain\Members\ExtraMembershipBillingItemRepositoryExpectation;
use Tests\Unit\Domain\Members\MemberRepositoryExpectation;
use Tests\UnitTestCase;

final class ApplyMemberVolunteerBillingTest extends UnitTestCase
{
    public function test_it_ensures_contribution_and_adds_restitution_for_volunteers(): void
    {
        $memberId = MemberId::create(1);
        $contributionId = BillableItemId::create(10);
        $restitutionId = BillableItemId::create(20);

        $memberRepository = MemberRepositoryExpectation::create();
        $extraMembershipBillingItemRepository = ExtraMembershipBillingItemRepositoryExpectation::create();
        $billableItemRepository = BillableItemRepositoryExpectation::create();

        $memberRepository->expectsGetById(
            $memberId,
            new Member($memberId, MembershipId::create(2), true)
        );
        $extraMembershipBillingItemRepository->expectsGetByCode(ExtraMembershipItemCode::VolunteerContribution, $contributionId);
        $extraMembershipBillingItemRepository->expectsGetByCode(ExtraMembershipItemCode::VolunteerRestitution, $restitutionId);
        $billableItemRepository->expectsEnsure($memberId, $contributionId);
        $billableItemRepository->expectsAddInstance($memberId, $restitutionId);

        $subject = new ApplyMemberVolunteerBilling(
            $extraMembershipBillingItemRepository->mock,
            $billableItemRepository->mock,
            $memberRepository->mock,
        );

        ($subject)($memberId, null);
    }

    public function test_it_ensures_contribution_and_removes_restitution_for_non_volunteers(): void
    {
        $memberId = MemberId::create(1);
        $contributionId = BillableItemId::create(10);
        $restitutionId = BillableItemId::create(20);

        $memberRepository = MemberRepositoryExpectation::create();
        $extraMembershipBillingItemRepository = ExtraMembershipBillingItemRepositoryExpectation::create();
        $billableItemRepository = BillableItemRepositoryExpectation::create();

        $memberRepository->expectsGetById(
            $memberId,
            new Member($memberId, MembershipId::create(2), false)
        );
        $extraMembershipBillingItemRepository->expectsGetByCode(ExtraMembershipItemCode::VolunteerContribution, $contributionId);
        $extraMembershipBillingItemRepository->expectsGetByCode(ExtraMembershipItemCode::VolunteerRestitution, $restitutionId);
        $billableItemRepository->expectsEnsure($memberId, $contributionId);
        $billableItemRepository->expectsRemoveInstances($memberId, new BillableItemIdList([$restitutionId]));

        $subject = new ApplyMemberVolunteerBilling(
            $extraMembershipBillingItemRepository->mock,
            $billableItemRepository->mock,
            $memberRepository->mock,
        );

        ($subject)($memberId, null);
    }
}
