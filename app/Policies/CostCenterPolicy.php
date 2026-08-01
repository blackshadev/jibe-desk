<?php

declare(strict_types=1);

namespace App\Policies;

use Override;

final class CostCenterPolicy extends ResourcePolicy
{
    #[Override]
    protected static function permissionPrefix(): string
    {
        return 'cost_centers';
    }
}
