<?php

declare(strict_types=1);

namespace App\Policies;

final class ExtraMembershipItemPolicy extends ResourcePolicy
{
    protected static function permissionPrefix(): string
    {
        return 'extra_membership_items';
    }
}
