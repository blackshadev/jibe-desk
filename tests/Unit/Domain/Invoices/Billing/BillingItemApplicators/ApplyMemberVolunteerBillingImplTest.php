<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices\Billing\BillingItemApplicators;

use App\Domain\Invoices\Billing\BillableItemId;
use App\Domain\Invoices\Billing\BillableItemIdList;
use App\Domain\Invoices\Billing\BillableItemInstanceId;
use App\Domain\Invoices\Billing\BillingItemApplicators\ApplyMemberVolunteerBillingImpl;
use App\Domain\Members\ExtraMembershipItemCode;
use App\Domain\Members\Member;
use App\Domain\Members\MemberId;
use App\Domain\Members\MembershipId;
use Tests\Unit\Domain\Invoices\BillableItemRepositoryExpectation;
use Tests\Unit\Domain\Members\ExtraMembershipBillingItemRepositoryExpectation;
use Tests\Unit\Domain\Members\MemberRepositoryExpectation;
use Tests\UnitTestCase;
use Override;

final class ApplyMemberVolunteerBillingImplTest extends UnitTestCase
{
    private MemberRepositoryExpectation $memberRepository;

    private ExtraMembershipBillingItemRepositoryExpectation $extraMembershipBillingItemRepository;

    private BillableItemRepositoryExpectation $billableItemRepository;

    private ApplyMemberVolunteerBillingImpl $subject;

    #[Override]
    protected function setup(): void
    {
        parent::setup();
        $this->memberRepository = MemberRepositoryExpectation::create();
        $this->extraMembershipBillingItemRepository = ExtraMembershipBillingItemRepositoryExpectation::create();
        $this->billableItemRepository = BillableItemRepositoryExpectation::create();

        $this->subject = new ApplyMemberVolunteerBillingImpl(
            $this->extraMembershipBillingItemRepository->mock,
            $this->billableItemRepository->mock,
            $this->memberRepository->mock,
        );
    }

    public function test_it_ensures_contribution_and_adds_restitution_for_volunteers(): void
    {
        $memberId = MemberId::create(1);
        $contributionId = BillableItemId::create(10);
        $restitutionId = BillableItemId::create(20);

        $this->memberRepository->expectsGetById(
            $memberId,
            new Member($memberId, MembershipId::create(2), true, null, 22),
        );
        $this->extraMembershipBillingItemRepository->expectsGetByCode(ExtraMembershipItemCode::VolunteerContribution, $contributionId);
        $this->extraMembershipBillingItemRepository->expectsGetByCode(ExtraMembershipItemCode::VolunteerRestitution, $restitutionId);
        $this->billableItemRepository->expectsEnsure($memberId, $contributionId);
        $this->billableItemRepository->expectsAdd($memberId, $restitutionId, null, BillableItemInstanceId::create(11));

        $this->subject->apply($memberId);
    }

    public function test_it_ensures_contribution_and_removes_restitution_for_non_volunteers(): void
    {
        $memberId = MemberId::create(1);
        $contributionId = BillableItemId::create(10);
        $restitutionId = BillableItemId::create(20);

        $this->memberRepository->expectsGetById(
            $memberId,
            new Member($memberId, MembershipId::create(2), false, null, 22),
        );
        $this->extraMembershipBillingItemRepository->expectsGetByCode(ExtraMembershipItemCode::VolunteerContribution, $contributionId);
        $this->extraMembershipBillingItemRepository->expectsGetByCode(ExtraMembershipItemCode::VolunteerRestitution, $restitutionId);
        $this->billableItemRepository->expectsEnsure($memberId, $contributionId);
        $this->billableItemRepository->expectsRemove($memberId, new BillableItemIdList([$restitutionId]));

        $this->subject->apply($memberId);
    }
}
