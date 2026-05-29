<?php

declare(strict_types=1);

namespace App\Observers;

use App\Domain\Activities\ActivityId;
use App\Domain\Invoices\Billing\BillableItemInstanceId;
use App\Domain\Invoices\Billing\BillingItemApplicators\ApplyActivityBilling;
use App\Domain\Members\MemberId;
use App\Models\Pivots\ActivityMember;

final readonly class ActivityMemberObserver
{
    public function __construct(private ApplyActivityBilling $applyActivityBilling)
    {
    }

    public function created(ActivityMember $member): void
    {
        $this->applyActivityBilling->apply(
            MemberId::create($member->member_id),
            ActivityId::create($member->activity_id),
        );
    }

    public function deleted(ActivityMember $member): void
    {
        if (!$member->billable_item_instance_id) {
            return;
        }

        $this->applyActivityBilling->stop(
            BillableItemInstanceId::create($member->billable_item_instance_id)
        );
    }
}
