<?php

declare(strict_types=1);

namespace App\Domain\Invoices\Billing\BillingItemApplicators;

use App\Domain\Activities\ActivityId;
use App\Domain\Activities\ActivityRepository;
use App\Domain\Invoices\Billing\BillableItemInstanceId;
use App\Domain\Invoices\Billing\BillableItemInstanceRepository;
use App\Domain\Members\MemberId;
use Override;

final readonly class ApplyActivityBillingImpl implements ApplyActivityBilling
{
    public function __construct(
        private ActivityRepository $activityRepository,
        private BillableItemInstanceRepository $billableItemInstanceRepository,
    ) {}

    #[Override]
    public function apply(MemberId $memberId, ActivityId $activityId): void
    {
        $activity = $this->activityRepository->getById(ActivityId::create($activityId->value));

        $instanceId = $this->billableItemInstanceRepository->add($memberId, $activity->billableItemId, $activity->endDate);

        $this->activityRepository->attach($activityId, $memberId, $instanceId);
    }

    #[Override]
    public function stop(BillableItemInstanceId $billableItemInstanceId): void
    {
        $this->billableItemInstanceRepository->stop($billableItemInstanceId);
    }
}
