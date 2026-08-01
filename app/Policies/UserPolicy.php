<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Override;
use Webmozart\Assert\Assert;

final class UserPolicy extends ResourcePolicy
{
    #[Override]
    protected static function permissionPrefix(): string
    {
        return 'users';
    }

    #[Override]
    public function delete(User $user, Model $model): bool
    {
        Assert::isInstanceOf($model, User::class);

        return $user->can('delete_users') && $user->id !== $model->id;
    }
}
