<?php

declare(strict_types=1);

namespace App\Domain\Invoices\Billing\BillingItemApplicators;

use App\Domain\Invoices\Billing\BillableItemInstanceRepository;
use App\Domain\Members\MemberId;
use Override;
use Psr\Clock\ClockInterface;

final readonly class ApplyStoppedMembershipImpl implements ApplyStoppedMembership
{
    public function __construct(
        private BillableItemInstanceRepository $billableItemInstanceRepository,
        private ClockInterface $clock,
    ) {}

    #[Override]
    public function apply(MemberId $memberId): void
    {
        $endDate = $this->clock->now();
        $this->billableItemInstanceRepository->stopAll($memberId, $endDate);
    }
}
