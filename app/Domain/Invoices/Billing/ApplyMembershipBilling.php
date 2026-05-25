<?php

declare(strict_types=1);

namespace App\Domain\Invoices\Billing;

use App\Domain\Members\MemberId;
use App\Domain\Members\MemberRepository;
use App\Domain\Members\MembershipId;
use App\Domain\Members\MembershipRepository;
use App\Domain\NumericId;
use Webmozart\Assert\Assert;

/** @implements ApplyBillableItem<MembershipId> */
final readonly class ApplyMembershipBilling implements ApplyBillableItem
{
    public function __construct(
        private MemberRepository $memberRepository,
        private MembershipRepository $membershipRepository,
        private BillableItemInstanceRepository $billableItemRepository,
    ) {
    }

    public function __invoke(MemberId $memberId, ?NumericId $membershipId): void
    {
        $member = $this->memberRepository->getById($memberId);

        $allBillingIds = $this->membershipRepository->all()->asBillingIdList();
        $this->billableItemRepository->removeMany($member->id, $allBillingIds);

        $newMembership = $this->membershipRepository->getById(MembershipId::create($membershipId->value));
        $this->billableItemRepository->add($memberId, $newMembership->billableItemId);
    }
}
