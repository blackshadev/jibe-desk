<?php

declare(strict_types=1);

namespace App\Domain\Billing\Subscriptions\BillingItemApplicators;

use App\Domain\Billing\Subscriptions\BillableItemInstanceRepository;
use App\Domain\Members\MemberId;
use DateTimeInterface;
use Override;

final readonly class ApplyStoppedMembershipImpl implements ApplyStoppedMembership
{
    public function __construct(
        private BillableItemInstanceRepository $billableItemInstanceRepository,
    ) {}

    #[Override]
    public function apply(MemberId $memberId, DateTimeInterface $stoppedAt): void
    {
        $this->billableItemInstanceRepository->stopAll($memberId, $stoppedAt);
    }

    #[Override]
    public function clear(MemberId $memberId, DateTimeInterface $stoppedAt): void
    {
        $this->billableItemInstanceRepository->reopenAll($memberId, $stoppedAt);
    }
}
