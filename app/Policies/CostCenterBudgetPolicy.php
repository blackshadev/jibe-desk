<?php

declare(strict_types=1);

namespace App\Policies;

final class CostCenterBudgetPolicy extends ResourcePolicy
{
    protected static function permissionPrefix(): string
    {
        return 'cost_center_budgets';
    }
}
