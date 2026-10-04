<?php

declare(strict_types=1);

namespace App\Infrastructure\Members;

use App\Domain\Members\MemberId;
use App\Domain\Members\MemberNameFormatter;
use App\Domain\Members\MemberUserRepository;
use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Str;
use Override;
use RuntimeException;

final readonly class MemberUserDbRepository implements MemberUserRepository
{
    #[Override]
    public function provision(MemberId $memberId): void
    {
        $member = Member::query()->find($memberId->value);

        if ($member === null || $member->user_id !== null) {
            return;
        }

        if (User::query()->where('email', $member->email)->exists()) {
            throw new RuntimeException('email address already in use by another user');
        }

        $user = User::forceCreate([
            'name' => MemberNameFormatter::presentationName(
                $member->first_name,
                $member->infix_name,
                $member->last_name,
            ),
            'email' => $member->email,
            'password' => Str::password(),
            'email_verified_at' => now(),
        ]);

        $member->forceFill(['user_id' => $user->id])->save();
    }
}
