<?php

declare(strict_types=1);

namespace App\Observers;

use App\Domain\Members\MemberId;
use App\Domain\Members\MembershipId;
use App\Events\MemberMembershipChanged;
use App\Events\MemberVolunteerChanged;
use App\Models\Member;
use Illuminate\Support\Facades\Event;

final class MemberObserver
{
    public function created(Member $member): void
    {
        Event::dispatch(new MemberMembershipChanged(
            memberId: MemberId::create($member->id),
            newMembershipId: MembershipId::create($member->membership_id),
        ));

        Event::dispatch(new MemberVolunteerChanged(MemberId::create($member->id)));
    }

    public function updated(Member $member): void
    {
        if ($member->wasChanged('membership_id')) {
            Event::dispatch(new MemberMembershipChanged(
                memberId: MemberId::create($member->id),
                newMembershipId: MembershipId::create($member->membership_id),
            ));
        }

        if ($member->wasChanged('is_volunteer')) {
            Event::dispatch(new MemberVolunteerChanged(MemberId::create($member->id)));
        }
    }
}
