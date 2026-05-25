<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domain\Invoices\Billing\ApplyMembershipBilling;
use App\Events\MemberMembershipChanged;
use Illuminate\Support\Facades\Log;

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
