<?php

declare(strict_types=1);

namespace App\Domain\Invoices\Billing\BillingItemApplicators;

use App\Domain\Members\MemberId;
use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface ApplyMemberVolunteerBilling
{
    public function apply(MemberId $memberId): void;
}
