<?php

declare(strict_types=1);

namespace App\Domain\Invoices\Billing\BillingItemApplicators;

use App\Domain\Members\MemberId;
use App\Domain\Members\MembershipId;
use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface ApplyMembershipBilling
{
    public function apply(MemberId $memberId, MembershipId $membershipId): void;
}
