<?php

declare(strict_types=1);

namespace App\Policies;

final class MembershipPolicy extends ResourcePolicy
{
    protected static function permissionPrefix(): string
    {
        return 'memberships';
    }
}
