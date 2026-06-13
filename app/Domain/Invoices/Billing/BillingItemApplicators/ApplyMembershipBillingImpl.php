<?php

declare(strict_types=1);

namespace App\Domain\Invoices\Billing\BillingItemApplicators;

use App\Domain\Invoices\Billing\BillableItemInstanceRepository;
use App\Domain\Members\MemberId;
use App\Domain\Members\MemberRepository;
use App\Domain\Members\MembershipId;
use App\Domain\Members\MembershipRepository;
use Override;

final readonly class ApplyMembershipBillingImpl implements ApplyMembershipBilling
{
    public function __construct(
        private MemberRepository $memberRepository,
        private MembershipRepository $membershipRepository,
        private BillableItemInstanceRepository $billableItemRepository,
    ) {}

    #[Override]
    public function apply(MemberId $memberId, MembershipId $membershipId): void
    {
        $member = $this->memberRepository->getById($memberId);

        $allBillingIds = $this->membershipRepository->all()->asBillingIdList();
        $this->billableItemRepository->removeMany($member->id, $allBillingIds);

        $newMembership = $this->membershipRepository->getById(MembershipId::create($membershipId->value));

        $billableItemId = $member->isYoungster()
            ? $newMembership->kidsBillableItemId
            : $newMembership->adultBillableItemId;

        $this->billableItemRepository->add($memberId, $billableItemId, null);
    }
}
