<?php

declare(strict_types=1);

namespace App\Domain\Invoices\Billing\BillingItemApplicators;

use App\Domain\Invoices\Billing\BillableItemIdList;
use App\Domain\Invoices\Billing\BillableItemInstanceRepository;
use App\Domain\Members\ExtraMembershipBillingItemRepository;
use App\Domain\Members\ExtraMembershipItemCode;
use App\Domain\Members\MemberId;
use App\Domain\Members\MemberRepository;

final readonly class ApplySameHouseholdBillingImpl implements ApplySameHouseholdBilling
{
    public function __construct(
        private ExtraMembershipBillingItemRepository $extraMembershipBillingItemRepository,
        private BillableItemInstanceRepository $billableItemInstanceRepository,
        private MemberRepository $memberRepository,
    ) {
    }

    public function apply(MemberId $memberId): void
    {
        $youngsterId = $this->extraMembershipBillingItemRepository->getByCode(ExtraMembershipItemCode::SameHouseholdDiscountYoungster);
        $adultId = $this->extraMembershipBillingItemRepository->getByCode(ExtraMembershipItemCode::SameHouseholdDiscountAdult);

        $this->billableItemInstanceRepository->removeMany($memberId, new BillableItemIdList([$youngsterId, $adultId]));

        $member = $this->memberRepository->getById($memberId);

        if (!$member->isInHousehold()) {
            return;
        }

        $discountId = $member->isYoungster() ? $youngsterId : $adultId;
        $this->billableItemInstanceRepository->add($memberId, $discountId, null);
    }
}
