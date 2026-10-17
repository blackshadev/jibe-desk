<?php

declare(strict_types=1);

namespace App\Domain\Billing\Subscriptions\BillingItemApplicators;

use App\Domain\Activities\ActivityId;
use App\Domain\Billing\Subscriptions\BillableItemInstanceId;
use App\Domain\Members\MemberId;
use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface ApplyActivityBilling
{
    public function apply(MemberId $memberId, ActivityId $activityId): void;

    public function stop(BillableItemInstanceId $billableItemInstanceId): void;
}
