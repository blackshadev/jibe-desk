<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets\Dashboard\Concerns;

use App\Domain\Authorization\RoleName;

/** @phpstan-ignore trait.unused (used by the follow-up dashboard widget plans) */
trait VisibleForRoles
{
    /**
     * @return list<RoleName>
     */
    abstract protected static function roles(): array;

    public static function canView(): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        return $user->hasAnyRole(
            array_map(static fn (RoleName $role): string => $role->value, static::roles()),
        );
    }
}
