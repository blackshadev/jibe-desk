<?php

declare(strict_types=1);

namespace App\Policies;

use Override;

final class ExtraMembershipItemPolicy extends ResourcePolicy
{
    #[Override]
    protected static function permissionPrefix(): string
    {
        return 'extra_membership_items';
    }
}
