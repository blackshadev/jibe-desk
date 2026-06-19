<?php

declare(strict_types=1);

namespace App\Policies;

final class ActivityPolicy extends ResourcePolicy
{
    protected static function permissionPrefix(): string
    {
        return 'activities';
    }
}
