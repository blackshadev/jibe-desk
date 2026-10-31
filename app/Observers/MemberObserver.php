<?php

declare(strict_types=1);

namespace App\Observers;

use App\Domain\Billing\Subscriptions\BillingItemApplicators\ApplyMembershipBilling;
use App\Domain\Billing\Subscriptions\BillingItemApplicators\ApplyMemberVolunteerBilling;
use App\Domain\Billing\Subscriptions\BillingItemApplicators\ApplyRegistrationFeeBilling;
use App\Domain\Billing\Subscriptions\BillingItemApplicators\ApplySameHouseholdBilling;
use App\Domain\Billing\Subscriptions\BillingItemApplicators\ApplyStoppedMembership;
use App\Domain\Members\MemberId;
use App\Domain\Members\MembershipId;
use App\Models\Member;
use Carbon\CarbonImmutable;

final readonly class MemberObserver
{
    public function __construct(
        private ApplyMemberVolunteerBilling $applyMemberVolunteerBilling,
        private ApplyMembershipBilling $applyMembershipBilling,
        private ApplySameHouseholdBilling $applySameHouseholdBilling,
        private ApplyStoppedMembership $applyStoppedMembership,
        private ApplyRegistrationFeeBilling $applyRegistrationFeeBilling,
    ) {}

    public function created(Member $member): void
    {
        if ($member->accepted_at === null) {
            return; // pending members are billed on acceptance (ApplyMemberAcceptedBilling)
        }

        $this->applyMembershipBilling->apply(
            MemberId::create($member->id),
            MembershipId::create($member->membership_id),
        );

        $this->applyMemberVolunteerBilling->apply(MemberId::create($member->id));
        $this->applySameHouseholdBilling->apply(MemberId::create($member->id));
        $this->applyRegistrationFeeBilling->apply(MemberId::create($member->id));
    }

    public function updated(Member $member): void
    {
        if ($member->wasChanged('membership_id')) {
            $this->applyMembershipBilling->apply(
                MemberId::create($member->id),
                MembershipId::create($member->membership_id),
            );
        }

        if ($member->wasChanged('is_volunteer')) {
            $this->applyMemberVolunteerBilling->apply(MemberId::create($member->id));
        }

        if ($member->wasChanged('household_id')) {
            $this->applySameHouseholdBilling->apply(MemberId::create($member->id));
        }

        if ($member->wasChanged('stopped_at') && $member->stopped_at !== null) {
            $this->applyStoppedMembership->apply(MemberId::create($member->id), $member->stopped_at);
        }

        if ($member->wasChanged('stopped_at') && $member->stopped_at === null) {
            $previousStopDate = CarbonImmutable::parse((string) $member->getOriginal('stopped_at'));
            $this->applyStoppedMembership->clear(MemberId::create($member->id), $previousStopDate);
        }
    }
}
