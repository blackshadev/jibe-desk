<?php

declare(strict_types=1);

namespace App\Domain\Billing\Subscriptions\BillingItemApplicators;

use App\Domain\Billing\Subscriptions\BillableItemInstanceRepository;
use App\Domain\Members\ExtraMembershipBillingItemRepository;
use App\Domain\Members\ExtraMembershipItemCode;
use App\Domain\Members\MemberId;
use Override;

final readonly class ApplyRegistrationFeeBillingImpl implements ApplyRegistrationFeeBilling
{
    public function __construct(
        private ExtraMembershipBillingItemRepository $extraMembershipBillingItemRepository,
        private BillableItemInstanceRepository $billableItemInstanceRepository,
    ) {}

    #[Override]
    public function apply(MemberId $memberId): void
    {
        $registrationFeeId = $this->extraMembershipBillingItemRepository
            ->getByCode(ExtraMembershipItemCode::Inschrijfgeld);

        $this->billableItemInstanceRepository->add($memberId, $registrationFeeId, null);
    }
}
