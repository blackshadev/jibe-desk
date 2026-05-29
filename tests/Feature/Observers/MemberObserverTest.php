<?php

declare(strict_types=1);

namespace Tests\Feature\Observers;

use App\Domain\Members\MemberId;
use App\Domain\Members\MembershipId;
use App\Models\Member;
use App\Models\Membership;
use App\Observers\MemberObserver;
use Tests\FeatureTestCase;
use Tests\Unit\Domain\Invoices\Billing\BillingItemApplicators\ApplyMembershipBillingExpectation;
use Tests\Unit\Domain\Invoices\Billing\BillingItemApplicators\ApplyMemberVolunteerBillingExpectation;

final class MemberObserverTest extends FeatureTestCase
{
    private ApplyMembershipBillingExpectation $applyMembership;

    private ApplyMemberVolunteerBillingExpectation $applyVolunteer;

    private MemberObserver $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->applyMembership = ApplyMembershipBillingExpectation::create();

        $this->applyVolunteer = ApplyMemberVolunteerBillingExpectation::create();

        $this->subject = new MemberObserver(
            $this->applyVolunteer->mock,
            $this->applyMembership->mock,
        );
    }

    public function test_it_applies_billing_on_created_members(): void
    {
        $member = Member::factory()->createQuietly();
        $memberId = new MemberId($member->id);
        $membershipId = new MembershipId($member->membership_id);

        $this->applyVolunteer->expectsApply($memberId);
        $this->applyMembership->expectsApply($memberId, $membershipId);

        $this->subject->created($member);
    }

    public function test_it_applies_membership_billing_on_membership_change(): void
    {
        $newMembership = Membership::factory()->create();
        $oldMembership = Membership::factory()->create();
        $member = Member::factory()
            ->for($oldMembership)
            ->createQuietly();

        $member->membership_id = $newMembership->id;
        $member->saveQuietly();

        $memberId = new MemberId($member->id);
        $membershipId = new MembershipId($newMembership->id);
        $this->applyMembership->expectsApply($memberId, $membershipId);
        $this->applyVolunteer->expectsApplyNever();

        $this->subject->updated($member);
    }

    public function test_it_does_not_apply_any_billing_on_irrelevant_changes(): void
    {
        $membership = Membership::factory()->create();
        $member = Member::factory()->createQuietly([
            'membership_id' => $membership->id,
            'first_name' => 'Old',
        ]);

        $member->first_name = 'New';
        $member->saveQuietly();

        $this->applyMembership->expectsApplyNever();
        $this->applyVolunteer->expectsApplyNever();

        $this->subject->updated($member);
    }

    public function test_it_applies_volunteering_billing_on_volunteer_change(): void
    {
        $membership = Membership::factory()->create();
        $member = Member::factory()->createQuietly([
            'membership_id' => $membership->id,
            'is_volunteer' => false,
        ]);

        $member->is_volunteer = true;
        $member->saveQuietly();

        $memberId = new MemberId($member->id);
        $this->applyMembership->expectsApplyNever();
        $this->applyVolunteer->expectsApply($memberId);

        $this->subject->updated($member);
    }
}
