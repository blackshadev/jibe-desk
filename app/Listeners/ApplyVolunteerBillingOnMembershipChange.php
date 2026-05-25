<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domain\Invoices\Billing\ApplyMembershipBilling;
use App\Domain\Invoices\Billing\ApplyMemberVolunteerBilling;
use App\Events\MemberMembershipChanged;
use App\Events\MemberVolunteerChanged;
use Illuminate\Support\Facades\Log;

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
