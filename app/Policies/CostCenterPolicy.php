<?php

declare(strict_types=1);

namespace App\Policies;

final class CostCenterPolicy extends ResourcePolicy
{
    protected static function permissionPrefix(): string
    {
        return 'cost_centers';
    }
}
