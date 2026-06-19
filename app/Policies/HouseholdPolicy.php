<?php

declare(strict_types=1);

namespace App\Policies;

final class HouseholdPolicy extends ResourcePolicy
{
    protected static function permissionPrefix(): string
    {
        return 'households';
    }
}
