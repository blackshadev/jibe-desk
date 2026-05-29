<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domain\Invoices\Billing\BillingItemApplicators\ApplyMemberVolunteerBilling;
use App\Events\MemberVolunteerChanged;

final readonly class ApplyVolunteerBillingOnMembershipChange
{
    public function __construct(
        private ApplyMemberVolunteerBilling $apply,
    ) {
    }

    public function handle(MemberVolunteerChanged $event): void
    {
        ($this->apply)($event->memberId, null);
    }
}
