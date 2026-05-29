<?php

declare(strict_types=1);

namespace App\Domain\Activities;

use App\Domain\Invoices\Billing\BillableItemInstanceId;
use App\Domain\Members\MemberId;
use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface ActivityRepository
{
    public function getById(ActivityId $activityId): Activity;

    public function attach(ActivityId $activityId, MemberId $memberId, BillableItemInstanceId $instanceId);
}
