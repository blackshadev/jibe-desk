<?php

declare(strict_types=1);

namespace App\Policies;

use Override;

final class InventoryCategoryPolicy extends ResourcePolicy
{
    #[Override]
    protected static function permissionPrefix(): string
    {
        return 'inventory_categories';
    }
}
