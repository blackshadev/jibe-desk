<?php

declare(strict_types=1);

namespace App\Policies;

final class MemberObjectPolicy extends ResourcePolicy
{
    protected static function permissionPrefix(): string
    {
        return 'member_objects';
    }
}
