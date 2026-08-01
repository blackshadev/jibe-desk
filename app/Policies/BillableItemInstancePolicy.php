<?php

declare(strict_types=1);

namespace App\Policies;

use Override;

final class BillableItemInstancePolicy extends ResourcePolicy
{
    #[Override]
    protected static function permissionPrefix(): string
    {
        return 'billable_item_instances';
    }
}
