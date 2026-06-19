<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

abstract class ResourcePolicy
{
    abstract protected static function permissionPrefix(): string;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_' . static::permissionPrefix());
    }

    public function view(User $user, Model $_record): bool
    {
        return $user->can('view_' . static::permissionPrefix());
    }

    public function create(User $user): bool
    {
        return $user->can('create_' . static::permissionPrefix());
    }

    public function update(User $user, Model $_record): bool
    {
        return $user->can('update_' . static::permissionPrefix());
    }

    public function delete(User $user, Model $_record): bool
    {
        return $user->can('delete_' . static::permissionPrefix());
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_' . static::permissionPrefix());
    }

    public function restore(User $user, Model $_record): bool
    {
        return $user->can('update_' . static::permissionPrefix());
    }

    public function forceDelete(User $user, Model $_record): bool
    {
        return $user->can('delete_' . static::permissionPrefix());
    }
}
