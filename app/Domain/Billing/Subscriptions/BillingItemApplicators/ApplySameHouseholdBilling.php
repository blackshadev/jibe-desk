<?php

declare(strict_types=1);

namespace App\Domain\Billing\Subscriptions\BillingItemApplicators;

use App\Domain\Members\MemberId;
use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface ApplySameHouseholdBilling
{
    public function apply(MemberId $memberId): void;
}
