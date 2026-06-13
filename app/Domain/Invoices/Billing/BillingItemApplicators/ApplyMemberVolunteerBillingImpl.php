<?php

declare(strict_types=1);

namespace App\Domain\Invoices\Billing\BillingItemApplicators;

use App\Domain\Invoices\Billing\BillableItemIdList;
use App\Domain\Invoices\Billing\BillableItemInstanceRepository;
use App\Domain\Members\ExtraMembershipBillingItemRepository;
use App\Domain\Members\ExtraMembershipItemCode;
use App\Domain\Members\MemberId;
use App\Domain\Members\MemberRepository;
use Override;

final readonly class ApplyMemberVolunteerBillingImpl implements ApplyMemberVolunteerBilling
{
    public function __construct(
        private ExtraMembershipBillingItemRepository $extraMembershipBillingItemRepository,
        private BillableItemInstanceRepository $billableItemInstanceRepository,
        private MemberRepository $memberRepository,
    ) {}

    #[Override]
    public function apply(MemberId $memberId): void
    {
        $contributionId = $this->extraMembershipBillingItemRepository->getByCode(ExtraMembershipItemCode::VolunteerContribution);
        $restitutionId = $this->extraMembershipBillingItemRepository->getByCode(ExtraMembershipItemCode::VolunteerRestitution);

        $this->billableItemInstanceRepository->ensure($memberId, $contributionId);

        $member = $this->memberRepository->getById($memberId);
        if ($member->isVolunteer) {
            // pass null endDate to match repository add signature
            $this->billableItemInstanceRepository->add($memberId, $restitutionId, null);
            return;
        }

        $this->billableItemInstanceRepository->removeMany($memberId, new BillableItemIdList([$restitutionId]));
    }
}
