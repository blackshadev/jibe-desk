<?php

declare(strict_types=1);

namespace Tests\Feature\Observers;

use App\Events\MemberMembershipChanged;
use App\Events\MemberVolunteerChanged;
use App\Models\Member;
use App\Models\Membership;
use App\Observers\MemberObserver;
use Illuminate\Support\Facades\Event;
use Tests\FeatureTestCase;

final class MemberObserverTest extends FeatureTestCase
{
    public function test_it_does_dispatch_event_when_created(): void
    {
        Event::fake();

        $member = Member::factory()->createQuietly();

        $observer = new MemberObserver();
        $observer->created($member);

        Event::assertDispatched(MemberMembershipChanged::class);
    }

    public function test_it_dispatches_event_when_membership_id_changes(): void
    {
        $newMembership = Membership::factory()->create();
        $oldMembership = Membership::factory()->create();
        $member = Member::factory()
            ->for($oldMembership)
            ->createQuietly();

        Event::fake();

        $member->membership_id = $newMembership->id;
        $member->save();

        $observer = new MemberObserver();
        $observer->updated($member);

        Event::assertDispatched(MemberMembershipChanged::class, function (MemberMembershipChanged $event) use ($member, $newMembership) {
            return $event->memberId->value === $member->id
                && $event->newMembershipId->value === $newMembership->id;
        });
    }

    public function test_it_does_not_dispatch_event_when_other_fields_change(): void
    {
        $membership = Membership::factory()->create();
        $member = Member::factory()->createQuietly([
            'membership_id' => $membership->id,
            'first_name' => 'Old',
        ]);

        Event::fake();

        $member->first_name = 'New';
        $member->save();

        $observer = new MemberObserver();
        $observer->updated($member);

        Event::assertNotDispatched(MemberMembershipChanged::class);
    }

    public function test_it_dispatches_member_volunteer_changed_when_is_volunteer_changes(): void
    {
        $membership = Membership::factory()->create();
        $member = Member::factory()->createQuietly([
            'membership_id' => $membership->id,
            'is_volunteer' => false,
        ]);

        Event::fake();

        $member->is_volunteer = true;
        $member->save();

        $observer = new MemberObserver();
        $observer->updated($member);

        Event::assertDispatched(MemberVolunteerChanged::class, function (MemberVolunteerChanged $event) use ($member): bool {
            return $event->memberId->value === $member->id;
        });
    }

    public function test_it_does_not_dispatch_member_volunteer_changed_when_other_fields_change(): void
    {
        $membership = Membership::factory()->create();
        $member = Member::factory()->createQuietly([
            'membership_id' => $membership->id,
            'is_volunteer' => false,
            'first_name' => 'Old',
        ]);

        Event::fake();

        $member->first_name = 'New';
        $member->save();

        $observer = new MemberObserver();
        $observer->updated($member);

        Event::assertNotDispatched(MemberVolunteerChanged::class);
    }
}
