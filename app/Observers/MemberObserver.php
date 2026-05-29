<?php

declare(strict_types=1);

namespace App\Observers;

use App\Domain\Invoices\Billing\BillingItemApplicators\ApplyMembershipBilling;
use App\Domain\Invoices\Billing\BillingItemApplicators\ApplyMemberVolunteerBilling;
use App\Domain\Members\MemberId;
use App\Domain\Members\MembershipId;
use App\Models\Member;

final readonly class MemberObserver
{
    public function __construct(
        private ApplyMemberVolunteerBilling $applyMemberVolunteerBilling,
        private ApplyMembershipBilling $applyMembershipBilling,
    ) {
    }

    public function created(Member $member): void
    {
        $this->applyMembershipBilling->apply(
            MemberId::create($member->id),
            MembershipId::create($member->membership_id),
        );

        $this->applyMemberVolunteerBilling->apply(MemberId::create($member->id));
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
    }
}
