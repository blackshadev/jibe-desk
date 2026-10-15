<?php

declare(strict_types=1);

namespace App\Domain\Members\Listeners;

use App\Domain\Billing\BillingItemApplicators\ApplyMembershipBilling;
use App\Domain\Billing\BillingItemApplicators\ApplyMemberVolunteerBilling;
use App\Domain\Billing\BillingItemApplicators\ApplyRegistrationFeeBilling;
use App\Domain\Billing\BillingItemApplicators\ApplySameHouseholdBilling;
use App\Domain\Members\Events\MemberAccepted;
use App\Domain\Members\MemberRepository;

final readonly class ApplyMemberAcceptedBilling
{
    public function __construct(
        private MemberRepository $memberRepository,
        private ApplyMembershipBilling $applyMembershipBilling,
        private ApplyMemberVolunteerBilling $applyMemberVolunteerBilling,
        private ApplySameHouseholdBilling $applySameHouseholdBilling,
        private ApplyRegistrationFeeBilling $applyRegistrationFeeBilling,
    ) {}

    public function handle(MemberAccepted $event): void
    {
        $member = $this->memberRepository->getById($event->memberId);

        $this->applyMembershipBilling->apply($event->memberId, $member->membershipId);
        $this->applyMemberVolunteerBilling->apply($event->memberId);
        $this->applySameHouseholdBilling->apply($event->memberId);
        $this->applyRegistrationFeeBilling->apply($event->memberId);
    }
}
