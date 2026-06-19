<?php

declare(strict_types=1);

namespace App\Policies;

final class BillableItemInstancePolicy extends ResourcePolicy
{
    protected static function permissionPrefix(): string
    {
        return 'billable_item_instances';
    }
}
