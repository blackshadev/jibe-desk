<?php

declare(strict_types=1);

namespace App\Infrastructure\Members;

use App\Domain\Members\Member as MemberEntity;
use App\Domain\Members\MemberId;
use App\Domain\Members\MemberRepository;
use App\Domain\Members\MembershipId;
use App\Models\Member;

final class MemberDbRepository implements MemberRepository
{
    public function getById(MemberId $memberId): MemberEntity
    {
        $model = Member::findOrFail($memberId->value);

        return new MemberEntity(
            id: MemberId::create($model->id),
            membershipId: MembershipId::create($model->membership_id),
            isVolunteer: $model->is_volunteer,
        );
    }
}
