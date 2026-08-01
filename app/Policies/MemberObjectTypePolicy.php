<?php

declare(strict_types=1);

namespace App\Policies;

use Override;

final class MemberObjectTypePolicy extends ResourcePolicy
{
    #[Override]
    protected static function permissionPrefix(): string
    {
        return 'member_object_types';
    }
}
