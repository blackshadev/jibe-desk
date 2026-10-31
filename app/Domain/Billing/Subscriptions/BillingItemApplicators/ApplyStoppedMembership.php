<?php

declare(strict_types=1);

namespace App\Domain\Billing\Subscriptions\BillingItemApplicators;

use App\Domain\Members\MemberId;
use DateTimeInterface;
use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface ApplyStoppedMembership
{
    public function apply(MemberId $memberId, DateTimeInterface $stoppedAt): void;

    public function clear(MemberId $memberId, DateTimeInterface $stoppedAt): void;
}
