<?php

declare(strict_types=1);

namespace App\Domain\Invoices\Billing;

use App\Domain\Members\ExtraMembershipBillingItemRepository;
use App\Domain\Members\ExtraMembershipItemCode;
use App\Domain\Members\MemberId;
use App\Domain\Members\MemberRepository;
use App\Domain\NumericId;

/** @implements ApplyBillableItem<null>  */
final readonly class ApplyMemberVolunteerBilling implements ApplyBillableItem
{
    public function __construct(
        private ExtraMembershipBillingItemRepository $extraMembershipBillingItemRepository,
        private BillableItemInstanceRepository $billableItemInstanceRepository,
        private MemberRepository $memberRepository,
    ) {
    }

    public function __invoke(MemberId $memberId, ?NumericId $null): void
    {
        $contributionId = $this->extraMembershipBillingItemRepository->getByCode(ExtraMembershipItemCode::VolunteerContribution);
        $restitutionId = $this->extraMembershipBillingItemRepository->getByCode(ExtraMembershipItemCode::VolunteerRestitution);

        $this->billableItemInstanceRepository->ensure($memberId, $contributionId);

        $member = $this->memberRepository->getById($memberId);
        if ($member->isVolunteer) {
            $this->billableItemInstanceRepository->add($memberId, $restitutionId);
        } else {
            $this->billableItemInstanceRepository->removeMany($memberId, new BillableItemIdList([$restitutionId]));
        }
    }
}
