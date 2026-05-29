<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domain\Invoices\Billing\BillingItemApplicators\ApplyMembershipBilling;
use App\Events\MemberMembershipChanged;

final readonly class ApplyMembershipBillingOnMembershipChange
{
    public function __construct(
        private ApplyMembershipBilling $applyMembershipBilling,
    ) {
    }

    public function handle(MemberMembershipChanged $event): void
    {
        ($this->applyMembershipBilling)($event->memberId, $event->newMembershipId);
    }
}
