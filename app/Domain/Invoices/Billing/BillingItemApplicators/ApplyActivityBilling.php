<?php

declare(strict_types=1);

namespace App\Domain\Invoices\Billing\BillingItemApplicators;

use App\Domain\Activities\ActivityId;
use App\Domain\Invoices\Billing\BillableItemInstanceId;
use App\Domain\Members\MemberId;
use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface ApplyActivityBilling
{
    public function apply(MemberId $memberId, ActivityId $activityId): void;

    public function stop(BillableItemInstanceId $billableItemInstanceId): void;
}
