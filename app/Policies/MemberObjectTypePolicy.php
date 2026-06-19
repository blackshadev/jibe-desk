<?php

declare(strict_types=1);

namespace App\Policies;

final class MemberObjectTypePolicy extends ResourcePolicy
{
    protected static function permissionPrefix(): string
    {
        return 'member_object_types';
    }
}
