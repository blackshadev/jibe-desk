<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Authorization\ResourcePermission;
use App\Domain\Authorization\RoleName;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (ResourcePermission::cases() as $permission) {
            Permission::firstOrCreate(['name' => $permission->value]);
        }

        $this->seedMemberAdministration();
        $this->seedFinancialAdministration();
        $this->seedActivityAdministration();
        $this->seedTechnicalAdministration();
        $this->seedRentalAdministration();
    }

    private function allPermissionsFor(string $resource): array
    {
        return collect(ResourcePermission::cases())
            ->filter(static fn (ResourcePermission $p) => str_ends_with($p->value, $resource))
            ->map(static fn (ResourcePermission $p) => $p->value)
            ->all();
    }

    /** @return list<string> */
    private function viewPermissionsFor(string $resource): array
    {
        return [
            "view_any_{$resource}",
            "view_{$resource}",
        ];
    }

    private function seedMemberAdministration(): void
    {
        $role = Role::firstOrCreate(['name' => RoleName::MemberAdministration->value]);

        $permissions = array_merge(
            $this->allPermissionsFor('members'),
            $this->allPermissionsFor('memberships'),
            $this->allPermissionsFor('households'),
            $this->viewPermissionsFor('member_objects'),
            $this->viewPermissionsFor('invoices'),
            $this->viewPermissionsFor('billable_item_instances'),
            $this->viewPermissionsFor('cost_centers'),
            $this->viewPermissionsFor('activities'),
            $this->viewPermissionsFor('storage_spaces'),
            $this->viewPermissionsFor('storage_space_locations'),
            $this->viewPermissionsFor('storage_space_rentals'),
            ['view_member_address_information', 'update_member_address_information'],
        );

        $role->syncPermissions($permissions);
    }

    private function seedFinancialAdministration(): void
    {
        $role = Role::firstOrCreate(['name' => RoleName::FinancialAdministration->value]);

        $permissions = array_merge(
            $this->viewPermissionsFor('members'),
            $this->viewPermissionsFor('memberships'),
            $this->viewPermissionsFor('households'),
            $this->viewPermissionsFor('member_objects'),
            $this->allPermissionsFor('invoices'),
            $this->allPermissionsFor('billable_item_instances'),
            $this->allPermissionsFor('cost_centers'),
            $this->allPermissionsFor('bookkeeping_records'),
            $this->allPermissionsFor('cost_center_budgets'),
            $this->allPermissionsFor('invoice_batches'),
            $this->allPermissionsFor('purchase_orders'),
            $this->viewPermissionsFor('activities'),
            $this->viewPermissionsFor('storage_spaces'),
            $this->viewPermissionsFor('storage_space_locations'),
            $this->viewPermissionsFor('storage_space_rentals'),
            ['view_member_payment_information', 'update_member_payment_information'],
        );

        $role->syncPermissions($permissions);
    }

    private function seedActivityAdministration(): void
    {
        $role = Role::firstOrCreate(['name' => RoleName::ActivityAdministration->value]);

        $permissions = array_merge(
            $this->viewPermissionsFor('members'),
            $this->viewPermissionsFor('memberships'),
            $this->allPermissionsFor('activities'),
        );

        $role->syncPermissions($permissions);
    }

    private function seedTechnicalAdministration(): void
    {
        $role = Role::firstOrCreate(['name' => RoleName::TechnicalAdministration->value]);

        $permissions = array_merge(
            $this->viewPermissionsFor('members'),
            $this->allPermissionsFor('outgoing_emails'),
            $this->allPermissionsFor('member_object_types'),
            $this->allPermissionsFor('extra_membership_items'),
            $this->allPermissionsFor('users'),
            $this->allPermissionsFor('storage_spaces'),
            $this->allPermissionsFor('storage_space_locations'),
            ['view_member_registration_data', 'update_member_registration_data'],
        );

        $role->syncPermissions($permissions);
    }

    private function seedRentalAdministration(): void
    {
        $role = Role::firstOrCreate(['name' => RoleName::RentalAdministration->value]);

        $permissions = array_merge(
            $this->viewPermissionsFor('storage_spaces'),
            $this->viewPermissionsFor('storage_space_locations'),
            $this->allPermissionsFor('storage_space_rentals'),
            $this->allPermissionsFor('member_objects'),
            $this->viewPermissionsFor('members'),
        );

        $role->syncPermissions($permissions);
    }
}
