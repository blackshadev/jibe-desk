<?php

declare(strict_types=1);

namespace App\Filament\Admin\Labels;

use App\Domain\Authorization\RoleName;

final class RoleLabels
{
    public static function label(RoleName $role): string
    {
        return __('labels.role_names.' . $role->value);
    }
}
