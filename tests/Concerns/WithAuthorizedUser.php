<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Domain\Authorization\RoleName;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\PermissionRegistrar;

trait WithAuthorizedUser
{
    private function seedPermissionsAndRoles(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        new RolePermissionSeeder()->run();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    protected function withAuthorizedUser(): User
    {
        $this->seedPermissionsAndRoles();

        $user = User::factory()->createQuietly();
        $user->syncRoles(RoleName::cases());

        $this->actingAs($user);

        return $user;
    }

    protected function withUserHavingRole(RoleName $roleName): User
    {
        $this->seedPermissionsAndRoles();

        $user = User::factory()->createQuietly();
        $user->assignRole($roleName->value);

        $this->actingAs($user);

        return $user;
    }
}
