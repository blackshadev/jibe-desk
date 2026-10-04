<?php

declare(strict_types=1);

namespace App\Infrastructure\Members;

use App\Domain\Members\MemberId;
use App\Domain\Members\MemberPasswordResetLink;
use App\Models\Member;
use Illuminate\Auth\Passwords\PasswordBroker;
use Override;
use RuntimeException;

final readonly class MemberPasswordResetLinkImpl implements MemberPasswordResetLink
{
    public function __construct(
        private PasswordBroker $passwordBroker,
    ) {}

    #[Override]
    public function generate(MemberId $memberId): string
    {
        $member = Member::find($memberId->value);
        $user = $member?->user;

        if ($user === null) {
            throw new RuntimeException("User not found for member ID: {$memberId->value}");
        }

        return url(route(
            'password.reset',
            [
                'token' => $this->passwordBroker->createToken($user),
                'email' => $user->getEmailForPasswordReset(),
            ],
            false,
        ));
    }
}
