# Implementation Plan: Roles & Permissions with spatie/laravel-permission

## Goal

Add role-based access control using `spatie/laravel-permission`, with 5 roles, granular permissions enforced via Laravel policies, field-level member permissions (payment/address/registration data restricted per role), a `UserResource` for managing users and their roles (checkboxes), and panel access control.

## Roles

| Slug (DB)                  | Dutch label         | Enum case                 |
|----------------------------|---------------------|---------------------------|
| `member_administration`    | Leden beheer        | `MemberAdministration`    |
| `invoicing`                | Facturatie          | `Invoicing`               |
| `activity_administration`  | Activiteiten beheer | `ActivityAdministration`  |
| `technical_administration` | Technisch beheer    | `TechnicalAdministration` |
| `rental_administration`    | Verhuur             | `RentalAdministration`    |

## Permission model

Following Spatie best practices: **users have roles, roles have permissions, the app checks permissions (not roles) in policies**.

Each resource gets standard CRUD permissions using the pattern `{action}_{resource}`:

| Action      | Permission key          |
|-------------|-------------------------|
| View list   | `view_any_{resource}`   |
| View single | `view_{resource}`       |
| Create      | `create_{resource}`     |
| Update      | `update_{resource}`     |
| Delete      | `delete_{resource}`     |
| Bulk delete | `delete_any_{resource}` |

### Member field-level permissions

The `MemberResource` form contains sensitive sections that must be restricted per role. These are **not** part of the standard CRUD permissions — they control visibility/editability of specific tabs within the member form.

| Section                              | Permission key                                                         | Roles that get view + update |
|--------------------------------------|------------------------------------------------------------------------|------------------------------|
| Payment information (financial data) | `view_member_payment_information`, `update_member_payment_information` | `invoicing`                  |
| Address information                  | `view_member_address_information`, `update_member_address_information` | `member_administration`      |
| Registration data                    | `view_member_registration_data`, `update_member_registration_data`     | `technical_administration`   |

> These permissions are **exclusive**: even though `member_administration` has full CRUD on `members`, they do NOT get access to `payment_information` or `registration_data` unless explicitly assigned. The `allPermissionsFor('members')` helper only matches CRUD permissions (ending with `_members`), not field-level permissions (ending with `_information` or `_data`).

### Resource → permission prefix mapping

| Resource             | Permission prefix         | Navigation group                            |
|----------------------|---------------------------|---------------------------------------------|
| Member               | `members`                 | MemberAdministration                        |
| Membership           | `memberships`             | MemberAdministration                        |
| Household            | `households`              | MemberAdministration                        |
| MemberObject         | `member_objects`          | MemberAdministration (via relation manager) |
| Invoice              | `invoices`                | Invoicing                                   |
| InvoiceBatch         | `invoice_batches`         | Invoicing                                   |
| Activity             | `activities`              | Activities                                  |
| OutgoingEmail        | `outgoing_emails`         | Technical                                   |
| MemberObjectType     | `member_object_types`     | Technical                                   |
| ExtraMembershipItem  | `extra_membership_items`  | Technical                                   |
| User                 | `users`                   | Technical                                   |
| StorageSpace         | `storage_spaces`          | Rental                                      |
| StorageSpaceLocation | `storage_space_locations` | Rental                                      |

### Role → permission assignment

| Permission prefix         | member_administration | invoicing          | activity_administration | technical_administration | rental_administration |
|---------------------------|-----------------------|--------------------|-------------------------|--------------------------|-----------------------|
| `members`                 | all                   | `view_any`, `view` | `view_any`, `view`      | `view_any`, `view`       |                       |
| `memberships`             | all                   | `view_any`, `view` | `view_any`, `view`      |                          |                       |
| `households`              | all                   | `view_any`, `view` |                         |                          |                       |
| `member_objects`          | all                   | `view_any`, `view` |                         |                          |                       |
| `invoices`                | `view_any`, `view`    | all                |                         |                          |                       |
| `invoice_batches`         |                       | all                |                         |                          |                       |
| `activities`              | `view_any`, `view`    | `view_any`, `view` | all                     |                          |                       |
| `outgoing_emails`         |                       |                    |                         | all                      |                       |
| `member_object_types`     |                       |                    |                         | all                      |                       |
| `extra_membership_items`  |                       |                    |                         | all                      |                       |
| `users`                   |                       |                    |                         | all                      |                       |
| `storage_spaces`          | `view_any`, `view`    | `view_any`, `view` |                         |                          | all                   |
| `storage_space_locations` | `view_any`, `view`    | `view_any`, `view` |                         |                          | all                   |

---

## Phase 1: Install spatie/laravel-permission

### Step 1.1 — Require the package

```bash
./Taskfile composer require spatie/laravel-permission
```

### Step 1.2 — Publish migration and config

```bash
./Taskfile artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
```

This publishes:
- `config/permission.php`
- `database/migrations/create_permission_tables.php.stub` (as a dated migration)

### Step 1.3 — Run migrations

```bash
./Taskfile artisan migrate
```

This creates the `roles`, `permissions`, `model_has_permissions`, `role_has_permissions`, and `model_has_roles` tables.

### Step 1.4 — Clear config cache

```bash
./Taskfile artisan config:clear
./Taskfile artisan optimize:clear
```

---

## Phase 2: User model changes

### Step 2.1 — Add `HasRoles` trait and `FilamentUser` contract

File: `app/Models/User.php`

Changes needed:
1. Add `use Spatie\Permission\Traits\HasRoles;` trait
2. Implement `Filament\Models\Contracts\FilamentUser`
3. Add `canAccessPanel(Panel $panel): bool` method
4. Add `member()` relation (forward to future `Member` link via `members.user_id`)

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Notifications\QueuedResetPassword;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Override;
use SensitiveParameter;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
final class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;
    use HasRoles;
    use Notifiable;

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            // @mago-ignore lint:no-literal-password
            'password' => 'hashed',
        ];
    }

    public function sendPasswordResetNotification(#[SensitiveParameter] $token): void
    {
        $this->notify(new QueuedResetPassword($token));
    }

    #[Override]
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasVerifiedEmail();
    }

}
```

---

## Phase 3: Role and Permission enums

### Step 3.1 — Create `RoleName` enum

File: `app/Domain/Authorization/RoleName.php`

```php
<?php

declare(strict_types=1);

namespace App\Domain\Authorization;

use Filament\Support\Contracts\HasLabel;
use Override;

enum RoleName: string implements HasLabel
{
    case MemberAdministration = 'member_administration';
    case Invoicing = 'invoicing';
    case ActivityAdministration = 'activity_administration';
    case TechnicalAdministration = 'technical_administration';
    case RentalAdministration = 'rental_administration';

    #[Override]
    public function getLabel(): string
    {
        return __('labels.role_names.' . $this->value);
    }
}
```

### Step 3.2 — Create `ResourcePermission` enum

File: `app/Domain/Authorization/ResourcePermission.php`

This enum lists all permission names as constants, making them type-safe and refactor-friendly.

```php
<?php

declare(strict_types=1);

namespace App\Domain\Authorization;

enum ResourcePermission: string
{
    // Members
    case ViewAnyMembers = 'view_any_members';
    case ViewMembers = 'view_members';
    case CreateMembers = 'create_members';
    case UpdateMembers = 'update_members';
    case DeleteMembers = 'delete_members';
    case DeleteAnyMembers = 'delete_any_members';

    // Member Field-Level Access (not part of standard CRUD)
    case ViewMemberPaymentInformation = 'view_member_payment_information';
    case UpdateMemberPaymentInformation = 'update_member_payment_information';
    case ViewMemberAddressInformation = 'view_member_address_information';
    case UpdateMemberAddressInformation = 'update_member_address_information';
    case ViewMemberRegistrationData = 'view_member_registration_data';
    case UpdateMemberRegistrationData = 'update_member_registration_data';

    // Memberships
    case ViewAnyMemberships = 'view_any_memberships';
    case ViewMemberships = 'view_memberships';
    case CreateMemberships = 'create_memberships';
    case UpdateMemberships = 'update_memberships';
    case DeleteMemberships = 'delete_memberships';
    case DeleteAnyMemberships = 'delete_any_memberships';

    // Households
    case ViewAnyHouseholds = 'view_any_households';
    case ViewHouseholds = 'view_households';
    case CreateHouseholds = 'create_households';
    case UpdateHouseholds = 'update_households';
    case DeleteHouseholds = 'delete_households';
    case DeleteAnyHouseholds = 'delete_any_households';

    // Member Objects
    case ViewAnyMemberObjects = 'view_any_member_objects';
    case ViewMemberObjects = 'view_member_objects';
    case CreateMemberObjects = 'create_member_objects';
    case UpdateMemberObjects = 'update_member_objects';
    case DeleteMemberObjects = 'delete_member_objects';
    case DeleteAnyMemberObjects = 'delete_any_member_objects';

    // Invoices
    case ViewAnyInvoices = 'view_any_invoices';
    case ViewInvoices = 'view_invoices';
    case CreateInvoices = 'create_invoices';
    case UpdateInvoices = 'update_invoices';
    case DeleteInvoices = 'delete_invoices';
    case DeleteAnyInvoices = 'delete_any_invoices';

    // Invoice Batches
    case ViewAnyInvoiceBatches = 'view_any_invoice_batches';
    case ViewInvoiceBatches = 'view_invoice_batches';
    case CreateInvoiceBatches = 'create_invoice_batches';
    case UpdateInvoiceBatches = 'update_invoice_batches';
    case DeleteInvoiceBatches = 'delete_invoice_batches';
    case DeleteAnyInvoiceBatches = 'delete_any_invoice_batches';

    // Activities
    case ViewAnyActivities = 'view_any_activities';
    case ViewActivities = 'view_activities';
    case CreateActivities = 'create_activities';
    case UpdateActivities = 'update_activities';
    case DeleteActivities = 'delete_activities';
    case DeleteAnyActivities = 'delete_any_activities';

    // Outgoing Emails
    case ViewAnyOutgoingEmails = 'view_any_outgoing_emails';
    case ViewOutgoingEmails = 'view_outgoing_emails';

    // Member Object Types
    case ViewAnyMemberObjectTypes = 'view_any_member_object_types';
    case ViewMemberObjectTypes = 'view_member_object_types';
    case CreateMemberObjectTypes = 'create_member_object_types';
    case UpdateMemberObjectTypes = 'update_member_object_types';
    case DeleteMemberObjectTypes = 'delete_member_object_types';
    case DeleteAnyMemberObjectTypes = 'delete_any_member_object_types';

    // Extra Membership Items
    case ViewAnyExtraMembershipItems = 'view_any_extra_membership_items';
    case ViewExtraMembershipItems = 'view_extra_membership_items';
    case CreateExtraMembershipItems = 'create_extra_membership_items';
    case UpdateExtraMembershipItems = 'update_extra_membership_items';
    case DeleteExtraMembershipItems = 'delete_extra_membership_items';
    case DeleteAnyExtraMembershipItems = 'delete_any_extra_membership_items';

    // Users
    case ViewAnyUsers = 'view_any_users';
    case ViewUsers = 'view_users';
    case CreateUsers = 'create_users';
    case UpdateUsers = 'update_users';
    case DeleteUsers = 'delete_users';
    case DeleteAnyUsers = 'delete_any_users';

    // Storage Spaces
    case ViewAnyStorageSpaces = 'view_any_storage_spaces';
    case ViewStorageSpaces = 'view_storage_spaces';
    case CreateStorageSpaces = 'create_storage_spaces';
    case UpdateStorageSpaces = 'update_storage_spaces';
    case DeleteStorageSpaces = 'delete_storage_spaces';
    case DeleteAnyStorageSpaces = 'delete_any_storage_spaces';

    // Storage Space Locations
    case ViewAnyStorageSpaceLocations = 'view_any_storage_space_locations';
    case ViewStorageSpaceLocations = 'view_storage_space_locations';
    case CreateStorageSpaceLocations = 'create_storage_space_locations';
    case UpdateStorageSpaceLocations = 'update_storage_space_locations';
    case DeleteStorageSpaceLocations = 'delete_storage_space_locations';
    case DeleteAnyStorageSpaceLocations = 'delete_any_storage_space_locations';
}
```

---

## Phase 4: RolePermissionSeeder

### Step 4.1 — Create the seeder

```bash
./Taskfile artisan make:seeder RolePermissionSeeder --no-interaction
```

File: `database/seeders/RolePermissionSeeder.php`

This seeder:
1. Creates all permissions from `ResourcePermission` enum
2. Creates all roles from `RoleName` enum
3. Assigns permissions to roles per the mapping table above

```php
<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Authorization\ResourcePermission;
use App\Domain\Authorization\RoleName;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

final class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermission();

        // Create all permissions
        foreach (ResourcePermission::cases() as $permission) {
            Permission::firstOrCreate(['name' => $permission->value]);
        }

        // Create roles and assign permissions
        $this->seedMemberAdministration();
        $this->seedInvoicing();
        $this->seedActivityAdministration();
        $this->seedTechnicalAdministration();
        $this->seedRentalAdministration();
    }

    /**
     * Get all CRUD permissions whose resource name ends with the given string.
     * Uses str_ends_with because permission values follow {action}_{resource},
     * e.g. 'view_any_members' ends with 'members'.
     * Field-level permissions like 'view_member_payment_information' do NOT
     * end with 'members', so they are excluded automatically.
     *
     * @return list<string>
     */
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
            $this->allPermissionsFor('member_objects'),
            $this->viewPermissionsFor('invoices'),
            $this->viewPermissionsFor('activities'),
            $this->viewPermissionsFor('storage_spaces'),
            $this->viewPermissionsFor('storage_space_locations'),
            // Field-level: only address information
            ['view_member_address_information', 'update_member_address_information'],
        );

        $role->syncPermissions($permissions);
    }

    private function seedInvoicing(): void
    {
        $role = Role::firstOrCreate(['name' => RoleName::FinancialAdministration->value]);

        $permissions = array_merge(
            $this->viewPermissionsFor('members'),
            $this->viewPermissionsFor('memberships'),
            $this->viewPermissionsFor('households'),
            $this->viewPermissionsFor('member_objects'),
            $this->allPermissionsFor('invoices'),
            $this->allPermissionsFor('invoice_batches'),
            $this->viewPermissionsFor('activities'),
            $this->viewPermissionsFor('storage_spaces'),
            $this->viewPermissionsFor('storage_space_locations'),
            // Field-level: only payment information
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
            // Field-level: only registration data
            ['view_member_registration_data', 'update_member_registration_data'],
        );

        $role->syncPermissions($permissions);
    }

    private function seedRentalAdministration(): void
    {
        $role = Role::firstOrCreate(['name' => RoleName::RentalAdministration->value]);

        $permissions = array_merge(
            $this->allPermissionsFor('storage_spaces'),
            $this->allPermissionsFor('storage_space_locations'),
        );

        $role->syncPermissions($permissions);
    }
}
```

### Step 4.2 — Register seeder in DatabaseSeeder

File: `database/seeders/DatabaseSeeder.php`

Add `$this->call(RolePermissionSeeder::class);` **before** other seeders (so roles exist when DevelopmentSeeder runs):

```php
public function run(): void
{
    $this->call(RolePermissionSeeder::class);  // <-- add this first

    $this->call(ActivitySeeder::class);
    $this->call(MembershipSeeder::class);
    $this->call(MemberObjectTypeSeeder::class);
    $this->call(StorageSpaceLocationSeeder::class);

    if (app()->environment('local')) {
        $this->call(DevelopmentSeeder::class);
    }
}
```

### Step 4.3 — Update DevelopmentSeeder to assign roles

File: `database/seeders/DevelopmentSeeder.php`

Update the existing test user creation (line 22-25) to assign a role, and add additional test users with different roles:

```php
use App\Domain\Authorization\RoleName;

// In run() method, replace the existing User::factory()->createQuietly(...) block:

$testUser = User::factory()->createQuietly([
    'email' => 'test@test.nl',
    'password' => Hash::make('password'),
]);
$testUser->assignRole(array_map(static fn (RoleName $role) => $role->value, RoleName::cases()));

// Add demo users for each role:
foreach (RoleName::cases() as $roleName) {
    $user = User::factory()->createQuietly([
        'email' => $roleName->value . '@test.nl',
        'password' => Hash::make('password'),
    ]);
    $user->assignRole($roleName->value);
}
```

---

## Phase 5: Authorization trait for policies

### Step 5.1 — Create `AuthorizesResource` trait

File: `app/Domain/Authorization/AuthorizesResource.php`

This trait provides standard CRUD policy methods that check Spatie permissions. It eliminates boilerplate across all policies. Each policy declares `permissionPrefix()` and can override any method for custom business logic.

```php
<?php

declare(strict_types=1);

namespace App\Domain\Authorization;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

trait AuthorizesResource
{
    /** The permission prefix, e.g. 'members', 'invoices'. */
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
```

---

## Phase 6: Create policies for all resources

Create one policy per resource model. Most use the `AuthorizesResource` trait with no overrides. The existing `InvoicePolicy` is updated to combine permission checks with its business logic.

### Step 6.1 — Generate policy files

```bash
./Taskfile artisan make:policy MemberPolicy --no-interaction
./Taskfile artisan make:policy MembershipPolicy --no-interaction
./Taskfile artisan make:policy HouseholdPolicy --no-interaction
./Taskfile artisan make:policy MemberObjectPolicy --no-interaction
./Taskfile artisan make:policy InvoiceBatchPolicy --no-interaction
./Taskfile artisan make:policy ActivityPolicy --no-interaction
./Taskfile artisan make:policy OutgoingEmailPolicy --no-interaction
./Taskfile artisan make:policy MemberObjectTypePolicy --no-interaction
./Taskfile artisan make:policy ExtraMembershipItemPolicy --no-interaction
./Taskfile artisan make:policy UserPolicy --no-interaction
./Taskfile artisan make:policy StorageSpacePolicy --no-interaction
./Taskfile artisan make:policy StorageSpaceLocationPolicy --no-interaction
```

### Step 6.2 — Simple policy example (MembershipPolicy)

File: `app/Policies/MembershipPolicy.php`

```php
<?php

declare(strict_types=1);

namespace App\Policies;

final class MembershipPolicy
{
    use AuthorizesResource;

    protected static function permissionPrefix(): string
    {
        return 'memberships';
    }
}
```

Apply this same pattern to:
- `HouseholdPolicy` → prefix: `households`
- `MemberObjectPolicy` → prefix: `member_objects`
- `InvoiceBatchPolicy` → prefix: `invoice_batches`
- `ActivityPolicy` → prefix: `activities`
- `OutgoingEmailPolicy` → prefix: `outgoing_emails` (only `viewAny` and `view` are defined in the enum; add `create`/`update`/`delete` returning `false` or omit them — Filament only checks methods that exist)
- `MemberObjectTypePolicy` → prefix: `member_object_types`
- `ExtraMembershipItemPolicy` → prefix: `extra_membership_items`
- `UserPolicy` → prefix: `users`
- `StorageSpacePolicy` → prefix: `storage_spaces`
- `StorageSpaceLocationPolicy` → prefix: `storage_space_locations`

### Step 6.3 — MemberPolicy (field-level permissions)

File: `app/Policies/MemberPolicy.php`

The `MemberPolicy` uses the `AuthorizesResource` trait for standard CRUD, plus explicit methods for field-level access. These methods are checked from the `MemberForm` via `auth()->user()->can(...)`.

```php
<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

final class MemberPolicy
{
    use AuthorizesResource;

    protected static function permissionPrefix(): string
    {
        return 'members';
    }

    public function viewPaymentInformation(User $user): bool
    {
        return $user->can('view_member_payment_information');
    }

    public function updatePaymentInformation(User $user): bool
    {
        return $user->can('update_member_payment_information');
    }

    public function viewAddressInformation(User $user): bool
    {
        return $user->can('view_member_address_information');
    }

    public function updateAddressInformation(User $user): bool
    {
        return $user->can('update_member_address_information');
    }

    public function viewRegistrationData(User $user): bool
    {
        return $user->can('view_member_registration_data');
    }

    public function updateRegistrationData(User $user): bool
    {
        return $user->can('update_member_registration_data');
    }
}
```

> These policy methods are not standard CRUD methods that Filament auto-checks. They are called explicitly from the `MemberForm` schema (see Step 6.7) via `auth()->user()->can('viewPaymentInformation', Member::class)` or directly via `auth()->user()->can('view_member_payment_information')`.

### Step 6.4 — Update InvoicePolicy (existing, has business logic)

File: `app/Policies/InvoicePolicy.php`

The existing policy has status-based business logic for `update` and `delete`. Combine permission check with business logic:

```php
<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Invoices\InvoiceStatus;use App\Models\Invoice;use App\Models\User;

final class InvoicePolicy
{
    use AuthorizesResource;

    protected static function permissionPrefix(): string
    {
        return 'invoices';
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $user->can('update_invoices')
            && $invoice->status === InvoiceStatus::Open;
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $user->can('delete_invoices')
            && $invoice->status === InvoiceStatus::Open;
    }
}
```

### Step 6.5 — OutgoingEmailPolicy (read-only resource)

File: `app/Policies/OutgoingEmailPolicy.php`

The `OutgoingEmailResource` only has a List page (no create/edit/delete). So the policy only needs `viewAny` and `view`:

```php
<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;use Illuminate\Database\Eloquent\Model;

final class OutgoingEmailPolicy
{
    use AuthorizesResource;

    protected static function permissionPrefix(): string
    {
        return 'outgoing_emails';
    }

    public function create(User $_user): bool
    {
        return false;
    }

    public function update(User $_user, Model $_record): bool
    {
        return false;
    }

    public function delete(User $_user, Model $_record): bool
    {
        return false;
    }
}
```

### Step 6.6 — UserPolicy (prevent self-deletion)

File: `app/Policies/UserPolicy.php`

```php
<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;use Illuminate\Database\Eloquent\Model;

final class UserPolicy
{
    use AuthorizesResource;

    protected static function permissionPrefix(): string
    {
        return 'users';
    }

    public function delete(User $user, Model $record): bool
    {
        /** @var User $record */
        return $user->can('delete_users') && $user->id !== $record->id;
    }
}
```

### Step 6.7 — Update MemberForm for field-level tab visibility

File: `app/Filament/Admin/Resources/Members/Schemas/MemberForm.php`

The existing form has 5 tabs: personal information, membership information, address information, payment information, and registration details. Three of those tabs must be conditionally visible based on field-level permissions, and their fields must be read-only when the user has view but not update permission.

Changes to the existing `MemberForm::configure()` method:

1. **Address information tab** — visible only when `view_member_address_information`; fields disabled when no `update_member_address_information`
2. **Payment information tab** — visible only when `view_member_payment_information`; fields disabled when no `update_member_payment_information`
3. **Registration details tab** — visible only when `view_member_registration_data`; fields disabled when no `update_member_registration_data`

The personal information and membership information tabs remain visible to anyone with `view_members` (controlled by the policy).

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Members\Schemas;

use App\Domain\Members\Gender;
use App\Models\Member;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;

final class MemberForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Tabs')
                    ->columnSpanFull()
                    ->columns(2)
                    ->tabs([
                        Tabs\Tab::make(__('labels.personal_information'))
                            ->schema([
                                TextInput::make('first_name')
                                    ->label(__('labels.first_name'))
                                    ->required(),

                                TextInput::make('infix_name')
                                    ->label(__('labels.infix_name')),

                                TextInput::make('last_name')
                                    ->label(__('labels.last_name'))
                                    ->required(),

                                Select::make('gender')
                                    ->label(__('labels.gender'))
                                    ->options([
                                        Gender::Male->value => __('labels.genders.' . Gender::Male->value),
                                        Gender::Female->value => __('labels.genders.' . Gender::Female->value),
                                        Gender::NonBinary->value => __('labels.genders.' . Gender::NonBinary->value),
                                        Gender::Unknown->value => __('labels.genders.' . Gender::Unknown->value),
                                        Gender::Other->value => __('labels.genders.' . Gender::Other->value),
                                    ])
                                    ->required(),

                                DatePicker::make('birthdate')
                                    ->format('d-m-Y')
                                    ->native(false)
                                    ->label(__('labels.birthdate'))
                                    ->required(),

                                TextInput::make('age')
                                    ->formatStateUsing(static fn (?Member $record) => $record?->age)
                                    ->disabled()
                                    ->label(__('labels.age'))
                                    ->required(),
                            ]),

                        Tabs\Tab::make(__('labels.membership_information'))
                            ->schema([
                                Select::make('membership')
                                    ->label(__('labels.membership'))
                                    ->relationship('membership', 'name')
                                    ->required(),

                                Toggle::make('is_volunteer')
                                    ->columnSpanFull()
                                    ->label(__('labels.is_volunteer')),
                            ]),

                        Tabs\Tab::make(__('labels.address_information'))
                            ->columns(12)
                            ->schema([
                                TextInput::make('address_street')
                                    ->columnSpan(6)
                                    ->required()
                                    ->label(__('labels.address_street'))
                                    ->disabled(fn (): bool => ! auth()->user()?->can('update_member_address_information')),

                                TextInput::make('address_housenumber')
                                    ->columnSpan(3)
                                    ->required()
                                    ->label(__('labels.address_housenumber'))
                                    ->disabled(fn (): bool => ! auth()->user()?->can('update_member_address_information')),

                                TextInput::make('address_housenumber_addition')
                                    ->columnSpan(3)
                                    ->label(__('labels.address_housenumber_addition'))
                                    ->disabled(fn (): bool => ! auth()->user()?->can('update_member_address_information')),

                                TextInput::make('address_postalcode')
                                    ->required()
                                    ->columnSpan(6)
                                    ->helperText(__('labels.address_postalcode_format'))
                                    ->regex('/^\d{4}[A-Z]{2}$/')
                                    ->label(__('labels.address_postalcode'))
                                    ->disabled(fn (): bool => ! auth()->user()?->can('update_member_address_information')),

                                TextInput::make('address_city')
                                    ->required()
                                    ->columnSpan(6)
                                    ->label(__('labels.address_city'))
                                    ->disabled(fn (): bool => ! auth()->user()?->can('update_member_address_information')),
                            ])
                            ->visible(fn (): bool => auth()->user()?->can('view_member_address_information') ?? false),

                        Tabs\Tab::make(__('labels.payment_information'))
                            ->schema([
                                Grid::make()
                                    ->relationship('paymentInformation')
                                    ->columns(2)
                                    ->columnSpanFull()
                                    ->schema([
                                        TextInput::make('banking_account_number')
                                            ->label(__('labels.banking_account_number'))
                                            ->maxLength(34)
                                            ->disabled(fn (): bool => ! auth()->user()?->can('update_member_payment_information')),

                                        TextInput::make('banking_bic')
                                            ->label(__('labels.banking_bic'))
                                            ->maxLength(11)
                                            ->disabled(fn (): bool => ! auth()->user()?->can('update_member_payment_information')),

                                        TextInput::make('banking_account_holder_name')
                                            ->label(__('labels.banking_account_holder_name'))
                                            ->columnSpanFull()
                                            ->maxLength(255)
                                            ->disabled(fn (): bool => ! auth()->user()?->can('update_member_payment_information')),

                                        DatePicker::make('mandate_accepted_date')
                                            ->format('d-m-Y')
                                            ->native(false)
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->label(__('labels.mandate_date')),

                                        TextInput::make('uuid')
                                            ->label(__('labels.reference'))
                                            ->disabled()
                                            ->dehydrated(false),
                                    ]),
                            ])
                            ->visible(fn (): bool => auth()->user()?->can('view_member_payment_information') ?? false),

                        Tabs\Tab::make(__('labels.registration_details'))
                            ->schema([
                                DatePicker::make('created_at')
                                    ->label(__('labels.created_at'))
                                    ->native(false)
                                    ->required()
                                    ->disabled(),
                                Tabs::make()
                                    ->vertical()
                                    ->columnSpanFull()
                                    ->schema([
                                        Tabs\Tab::make(__('labels.membership_information'))
                                            ->schema([
                                                KeyValue::make('registration_data.membership')
                                                    ->columnSpanFull()
                                                    ->hiddenLabel()
                                                    ->disabled(),
                                            ]),
                                        Tabs\Tab::make(__('labels.personal_information'))
                                            ->schema([
                                                KeyValue::make('registration_data.personalInfo')
                                                    ->columnSpanFull()
                                                    ->hiddenLabel()
                                                    ->disabled(),
                                            ]),
                                        Tabs\Tab::make(__('labels.payment_information'))
                                            ->schema([
                                                KeyValue::make('registration_data.paymentInfo')
                                                    ->columnSpanFull()
                                                    ->hiddenLabel()
                                                    ->disabled(),
                                            ]),
                                    ]),
                            ])
                            ->visible(fn (): bool => auth()->user()?->can('view_member_registration_data') ?? false),
                    ]),
            ]);
    }
}
```

> **Key points:**
> - `->visible(fn (): bool => auth()->user()?->can('view_member_...') ?? false)` hides the entire tab when the user lacks the view permission.
> - `->disabled(fn (): bool => ! auth()->user()?->can('update_member_...'))` makes individual fields read-only when the user has view but not update permission. Disabled fields are still dehydrated by default, so their values are preserved on save.
> - The `mandate_accepted_date`, `uuid`, and `created_at` fields are always disabled (they are system-managed, not user-editable).
> - The `registration_data` KeyValue fields are always disabled (they are historical snapshots, not editable).

---

## Phase 7: UserResource (Filament)

Follow the exact directory structure pattern used by existing resources (e.g., `MemberResource`).

### Step 7.1 — Create directory structure

```
app/Filament/Admin/Resources/Users/
├── UserResource.php
├── Pages/
│   ├── ListUsers.php
│   ├── CreateUser.php
│   └── EditUser.php
├── Schemas/
│   └── UserForm.php
└── Tables/
    └── UsersTable.php
```

### Step 7.2 — UserResource

File: `app/Filament/Admin/Resources/Users/UserResource.php`

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Users;

use App\Filament\Admin\Navigation\NavigationGroup;
use App\Filament\Admin\Resources\Users\Pages\CreateUser;
use App\Filament\Admin\Resources\Users\Pages\EditUser;
use App\Filament\Admin\Resources\Users\Pages\ListUsers;
use App\Filament\Admin\Resources\Users\Schemas\UserForm;
use App\Filament\Admin\Resources\Users\Tables\UsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Override;
use UnitEnum;

final class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Technical;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Users;

    protected static ?string $recordTitleAttribute = 'name';

    #[Override]
    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }

    #[Override]
    public static function getPluralLabel(): string
    {
        return __('labels.users');
    }

    #[Override]
    public static function getLabel(): string
    {
        return __('labels.user');
    }
}
```

### Step 7.3 — UserForm (with role checkboxes)

File: `app/Filament/Admin/Resources/Users/Schemas/UserForm.php`

The roles are displayed as a `CheckboxList` using the `roles` relationship from Spatie's `HasRoles` trait. The checkbox labels come from the `RoleName` enum's `getLabel()` method.

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Users\Schemas;

use App\Domain\Authorization\RoleName;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('labels.personal_information'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label(__('labels.name'))
                            ->required(),

                        TextInput::make('email')
                            ->label(__('labels.email'))
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true),
                    ]),

                Section::make(__('labels.password'))
                    ->schema([
                        TextInput::make('password')
                            ->label(__('labels.password'))
                            ->password()
                            ->revealable()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->maxLength(255),
                    ]),

                Section::make(__('labels.roles'))
                    ->schema([
                        CheckboxList::make('roles')
                            ->label(__('labels.roles'))
                            ->relationship('roles', 'name')
                            ->getOptionLabelFromRecordUsing(
                                static fn (\Spatie\Permission\Models\Role $record): string =>
                                    RoleName::tryFrom($record->name)?->getLabel() ?? $record->name
                            )
                            ->searchable()
                            ->columns(2)
                            ->bulkToggleable(),
                    ]),
            ]);
    }
}
```

### Step 7.4 — UsersTable

File: `app/Filament/Admin/Resources/Users/Tables/UsersTable.php`

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('labels.name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label(__('labels.email'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('roles.name')
                    ->label(__('labels.roles'))
                    ->badge()
                    ->formatStateUsing(
                        static fn (string $state): string =>
                            \App\Domain\Authorization\RoleName::tryFrom($state)?->getLabel() ?? $state
                    ),

                TextColumn::make('created_at')
                    ->label(__('labels.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->searchable(['name', 'email']);
    }
}
```

### Step 7.5 — ListUsers page

File: `app/Filament/Admin/Resources/Users/Pages/ListUsers.php`

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Users\Pages;

use App\Filament\Admin\Resources\Users\UserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Override;

final class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
```

### Step 7.6 — CreateUser page

File: `app/Filament/Admin/Resources/Users/Pages/CreateUser.php`

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Users\Pages;

use App\Filament\Admin\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;
use Override;

final class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    #[Override]
    protected function getCreatedNotificationTitle(): string
    {
        return __('notifications.user_created');
    }
}
```

### Step 7.7 — EditUser page

File: `app/Filament/Admin/Resources/Users/Pages/EditUser.php`

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Users\Pages;

use App\Filament\Admin\Resources\Users\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Override;

final class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->after(static function (): void {
                    Notification::make()->success()->title(__('notifications.user_deleted'))->send();
                }),
        ];
    }

    #[Override]
    protected function getSavedNotification(): Notification
    {
        return Notification::make()->success()->title(__('notifications.user_updated'));
    }
}
```

---

## Phase 8: Labels and translations

### Step 8.1 — Add labels to lang file

File: `lang/nl/labels.php`

Add these entries (e.g., after the `navigation_groups` block around line 63):

```php
'user' => 'Gebruiker',
'users' => 'Gebruikers',
'password' => 'Wachtwoord',
'roles' => 'Rollen',
'role' => 'Rol',
'role_names' => [
    'member_administration' => 'Leden beheer',
    'invoicing' => 'Facturatie',
    'activity_administration' => 'Activiteiten beheer',
    'technical_administration' => 'Technisch beheer',
    'rental_administration' => 'Verhuur',
],
```

> **Note**: The `role_names` sub-array maps English role slugs to Dutch display names. `RoleName::getLabel()` reads `__('labels.role_names.' . $this->value)`, so the enum value (`member_administration`) becomes the key, and the Dutch label is the value. The `'roles' => 'Rollen'` key is used as the section title and CheckboxList label.

### Step 8.2 — Add notifications

File: `lang/nl/notifications.php`

```php
'user_created' => 'Gebruiker aangemaakt',
'user_updated' => 'Gebruiker bijgewerkt',
'user_deleted' => 'Gebruiker verwijderd',
```

---

## Phase 9: Update existing tests

### The problem

Once policies are added, Filament will check them for all resource operations. Existing Filament resource tests (e.g., `StorageSpaceResourceTest`, `InvoiceBatchResourceTest`) run without an authenticated user. The policies will return `false` for unauthenticated users, causing tests to fail with 403 errors.

### Step 9.1 — Create a test helper trait

File: `tests/Concerns/WithAuthorizedUser.php`

```php
<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Domain\Authorization\ResourcePermission;
use App\Domain\Authorization\RoleName;
use App\Models\User;
use Spatie\Permission\Models\Role;

trait WithAuthorizedUser
{
    /**
     * Create and authenticate a user with all permissions.
     * Use this for existing tests that need full access.
     */
    protected function withAuthorizedUser(): User
    {
        $role = Role::firstOrCreate(['name' => 'full_access']);

        $permissions = collect(ResourcePermission::cases())
            ->map(static fn (ResourcePermission $p) => $p->value)
            ->all();

        $role->syncPermissions($permissions);

        $user = User::factory()->createQuietly();
        $user->assignRole($role);

        $this->actingAs($user);

        return $user;
    }

    /**
     * Create and authenticate a user with a specific role.
     */
    protected function withUserHavingRole(RoleName $roleName): User
    {
        $user = User::factory()->createQuietly();
        $user->assignRole($roleName->value);

        $this->actingAs($user);

        return $user;
    }
}
```

### Step 9.2 — Update existing Filament resource tests

For each existing Filament resource test, add `use WithAuthorizedUser;` and call `$this->withAuthorizedUser()` at the start of each test method.

**Files to update:**

1. `tests/Feature/Filament/StorageSpaces/StorageSpaceResourceTest.php`
   - Add `use Tests\Concerns\WithAuthorizedUser;`
   - Add `use WithAuthorizedUser;` inside the class
   - Call `$this->withAuthorizedUser();` at the start of each `test_*` method

2. `tests/Feature/Filament/InvoiceBatches/InvoiceBatchResourceTest.php`
   - Same pattern — though this test doesn't use Livewire, it tests the model directly. Check if it actually needs authentication. If it only tests model behavior (factory, update), no auth is needed. Only add auth if the test goes through Filament routes/Livewire.

> **Important**: Only tests that go through Filament's Livewire components or HTTP routes need authentication. Tests that directly test model methods (like `InvoiceBatchResourceTest` which tests factory creation and model updates) do NOT need authentication.

---

## Phase 10: New tests

### Step 10.1 — UserResource test

File: `tests/Feature/Filament/Users/UserResourceTest.php`

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Users;

use App\Domain\Authorization\RoleName;
use App\Filament\Admin\Resources\Users\Pages\CreateUser;
use App\Filament\Admin\Resources\Users\Pages\EditUser;
use App\Filament\Admin\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Livewire\Livewire;
use Tests\Concerns\WithAuthorizedUser;
use Tests\FeatureTestCase;

final class UserResourceTest extends FeatureTestCase
{
    use WithAuthorizedUser;

    public function test_can_list_users(): void
    {
        $this->withAuthorizedUser();

        $user = User::factory()->createQuietly();

        Livewire::test(ListUsers::class)
            ->assertCanSeeTableRecords([$user]);
    }

    public function test_can_create_user_with_roles(): void
    {
        $this->withAuthorizedUser();

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Test User',
                'email' => 'testuser@example.com',
                'password' => 'password123',
                'roles' => [RoleName::MemberAdministration->value],
            ])
            ->call('create');

        $this->assertDatabaseHas('users', [
            'email' => 'testuser@example.com',
        ]);

        $user = User::where('email', 'testuser@example.com')->first();
        static::assertTrue($user->hasRole(RoleName::MemberAdministration->value));
    }

    public function test_can_edit_user_roles(): void
    {
        $this->withAuthorizedUser();

        $user = User::factory()->createQuietly();
        $user->assignRole(RoleName::FinancialAdministration->value);

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->fillForm([
                'name' => $user->name,
                'email' => $user->email,
                'roles' => [
                    RoleName::FinancialAdministration->value,
                    RoleName::ActivityAdministration->value,
                ],
            ])
            ->call('save');

        $user->refresh();
        static::assertTrue($user->hasRole(RoleName::FinancialAdministration->value));
        static::assertTrue($user->hasRole(RoleName::ActivityAdministration->value));
    }

    public function test_cannot_delete_self(): void
    {
        $admin = $this->withAuthorizedUser();

        Livewire::test(EditUser::class, ['record' => $admin->getRouteKey()])
            ->callAction('delete')
            ->assertHasActionAuthorizationError('delete');

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }
}
```

### Step 10.2 — Authorization test

File: `tests/Feature/Authorization/AuthorizationTest.php`

Tests that users with specific roles can/cannot access specific resources.

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Domain\Authorization\RoleName;
use App\Filament\Admin\Resources\Members\Pages\ListMembers;
use App\Filament\Admin\Resources\Invoices\Pages\ListInvoices;
use App\Filament\Admin\Resources\Activities\Pages\ListActivities;
use App\Filament\Admin\Resources\OutgoingEmails\Pages\ListOutgoingEmails;
use App\Filament\Admin\Resources\StorageSpaces\Pages\ListStorageSpaces;
use App\Filament\Admin\Resources\InvoiceBatches\Pages\ListInvoiceBatches;
use App\Models\User;
use Livewire\Livewire;
use Tests\Concerns\WithAuthorizedUser;
use Tests\FeatureTestCase;

final class AuthorizationTest extends FeatureTestCase
{
    use WithAuthorizedUser;

    // --- Resource access: positive cases ---

    public function test_member_administration_can_view_members(): void
    {
        $this->withUserHavingRole(RoleName::MemberAdministration);

        Livewire::test(ListMembers::class)
            ->assertSuccessful();
    }

    public function test_invoicing_can_view_invoices(): void
    {
        $this->withUserHavingRole(RoleName::FinancialAdministration);

        Livewire::test(ListInvoices::class)
            ->assertSuccessful();
    }

    public function test_invoicing_can_view_invoice_batches(): void
    {
        $this->withUserHavingRole(RoleName::FinancialAdministration);

        Livewire::test(ListInvoiceBatches::class)
            ->assertSuccessful();
    }

    public function test_activity_administration_can_view_activities(): void
    {
        $this->withUserHavingRole(RoleName::ActivityAdministration);

        Livewire::test(ListActivities::class)
            ->assertSuccessful();
    }

    public function test_technical_administration_can_view_outgoing_emails(): void
    {
        $this->withUserHavingRole(RoleName::TechnicalAdministration);

        Livewire::test(ListOutgoingEmails::class)
            ->assertSuccessful();
    }

    public function test_rental_administration_can_view_storage_spaces(): void
    {
        $this->withUserHavingRole(RoleName::RentalAdministration);

        Livewire::test(ListStorageSpaces::class)
            ->assertSuccessful();
    }

    // --- Resource access: cross-role view permissions ---

    public function test_activity_administration_can_view_members(): void
    {
        $this->withUserHavingRole(RoleName::ActivityAdministration);

        Livewire::test(ListMembers::class)
            ->assertSuccessful();
    }

    public function test_invoicing_can_view_members(): void
    {
        $this->withUserHavingRole(RoleName::FinancialAdministration);

        Livewire::test(ListMembers::class)
            ->assertSuccessful();
    }

    public function test_member_administration_can_view_invoices(): void
    {
        $this->withUserHavingRole(RoleName::MemberAdministration);

        Livewire::test(ListInvoices::class)
            ->assertSuccessful();
    }

    // --- Resource access: negative cases ---

    public function test_activity_administration_cannot_view_invoices(): void
    {
        $this->withUserHavingRole(RoleName::ActivityAdministration);

        Livewire::test(ListInvoices::class)
            ->assertForbidden();
    }

    public function test_rental_administration_cannot_view_members(): void
    {
        $this->withUserHavingRole(RoleName::RentalAdministration);

        Livewire::test(ListMembers::class)
            ->assertForbidden();
    }

    public function test_member_administration_cannot_view_outgoing_emails(): void
    {
        $this->withUserHavingRole(RoleName::MemberAdministration);

        Livewire::test(ListOutgoingEmails::class)
            ->assertForbidden();
    }

    public function test_user_without_role_cannot_access_resources(): void
    {
        $user = User::factory()->createQuietly();
        $this->actingAs($user);

        Livewire::test(ListMembers::class)
            ->assertForbidden();
    }

    // --- Member field-level permissions ---

    public function test_invoicing_can_view_member_payment_information(): void
    {
        $user = $this->withUserHavingRole(RoleName::FinancialAdministration);

        static::assertTrue($user->can('view_member_payment_information'));
        static::assertTrue($user->can('update_member_payment_information'));
    }

    public function test_member_administration_cannot_view_member_payment_information(): void
    {
        $user = $this->withUserHavingRole(RoleName::MemberAdministration);

        static::assertFalse($user->can('view_member_payment_information'));
    }

    public function test_member_administration_can_view_member_address_information(): void
    {
        $user = $this->withUserHavingRole(RoleName::MemberAdministration);

        static::assertTrue($user->can('view_member_address_information'));
        static::assertTrue($user->can('update_member_address_information'));
    }

    public function test_invoicing_cannot_view_member_address_information(): void
    {
        $user = $this->withUserHavingRole(RoleName::FinancialAdministration);

        static::assertFalse($user->can('view_member_address_information'));
    }

    public function test_technical_administration_can_view_member_registration_data(): void
    {
        $user = $this->withUserHavingRole(RoleName::TechnicalAdministration);

        static::assertTrue($user->can('view_member_registration_data'));
        static::assertTrue($user->can('update_member_registration_data'));
    }

    public function test_member_administration_cannot_view_member_registration_data(): void
    {
        $user = $this->withUserHavingRole(RoleName::MemberAdministration);

        static::assertFalse($user->can('view_member_registration_data'));
    }
}
```

> **Note**: The `withUserHavingRole()` method needs to be added to the `WithAuthorizedUser` trait as well (shown in Step 9.1).

---

## Phase 11: Gate::before for super-admin (optional)

If a super-admin bypass is needed in the future, add a `Gate::before` callback in `AppServiceProvider::boot()`:

File: `app/Providers/AppServiceProvider.php`

```php
use Illuminate\Support\Facades\Gate;

public function boot(): void
{
    RateLimiter::for('invoice-emails', static fn (): Limit => Limit::perMinute(20));

    Gate::before(static function (User $user, string $ability): ?bool {
        if ($user->hasRole('super_admin')) {
            return true;
        }
        return null;
    });
}
```

This is **optional** and not required for the 5 roles specified. Only add if a super-admin role is desired.

---

## Implementation order summary

1. **Phase 1**: Install `spatie/laravel-permission`, publish, migrate
2. **Phase 2**: Update `User` model (`HasRoles`, `FilamentUser`, `canAccessPanel`, `member()` relation)
3. **Phase 3**: Create `RoleName` and `ResourcePermission` enums
4. **Phase 4**: Create `RolePermissionSeeder`, register in `DatabaseSeeder`, update `DevelopmentSeeder`
5. **Phase 5**: Create `AuthorizesResource` trait
6. **Phase 6**: Create all policies (12 new + 1 updated `InvoicePolicy`), update `MemberForm` for field-level tab visibility
7. **Phase 7**: Create `UserResource` with all pages, form, table
8. **Phase 8**: Add labels and notifications to lang files
9. **Phase 9**: Create `WithAuthorizedUser` test helper, update existing tests
10. **Phase 10**: Write new tests for `UserResource` and authorization
11. **Phase 11** (optional): Add `Gate::before` super-admin bypass

## Verification

After implementation, run:

```bash
# Run the seeder
./Taskfile artisan db:seed --class=RolePermissionSeeder

# Verify roles and permissions exist
./Taskfile artisan tinker --execute 'echo \Spatie\Permission\Models\Role::pluck("name")->toJson();'
./Taskfile artisan tinker --execute 'echo \Spatie\Permission\Models\Permission::count();'

# Run tests
./Taskfile artisan test --compact --filter=UserResource
./Taskfile artisan test --compact --filter=Authorization
./Taskfile artisan test --compact tests/Feature/Filament/StorageSpaces/StorageSpaceResourceTest.php

# Full test suite
./Taskfile artisan test --compact
```

## Files created (new)

| File | Purpose |
|---|---|
| `app/Domain/Authorization/RoleName.php` | Role enum (5 roles, English slugs, Dutch labels via `role_names` translations) |
| `app/Domain/Authorization/ResourcePermission.php` | Permission enum (all permission names including member field-level) |
| `app/Domain/Authorization/AuthorizesResource.php` | Trait for standard CRUD policy methods |
| `database/seeders/RolePermissionSeeder.php` | Seeds roles, permissions, and assignments |
| `app/Policies/MemberPolicy.php` | Member authorization with field-level permission methods (payment/address/registration) |
| `app/Policies/MembershipPolicy.php` | Membership authorization |
| `app/Policies/HouseholdPolicy.php` | Household authorization |
| `app/Policies/MemberObjectPolicy.php` | Member object authorization |
| `app/Policies/InvoiceBatchPolicy.php` | Invoice batch authorization |
| `app/Policies/ActivityPolicy.php` | Activity authorization |
| `app/Policies/OutgoingEmailPolicy.php` | Outgoing email authorization (read-only) |
| `app/Policies/MemberObjectTypePolicy.php` | Member object type authorization |
| `app/Policies/ExtraMembershipItemPolicy.php` | Extra membership item authorization |
| `app/Policies/UserPolicy.php` | User authorization (prevents self-deletion) |
| `app/Policies/StorageSpacePolicy.php` | Storage space authorization |
| `app/Policies/StorageSpaceLocationPolicy.php` | Storage space location authorization |
| `app/Filament/Admin/Resources/Users/UserResource.php` | Filament resource for users |
| `app/Filament/Admin/Resources/Users/Pages/ListUsers.php` | List users page |
| `app/Filament/Admin/Resources/Users/Pages/CreateUser.php` | Create user page |
| `app/Filament/Admin/Resources/Users/Pages/EditUser.php` | Edit user page |
| `app/Filament/Admin/Resources/Users/Schemas/UserForm.php` | User form with role checkboxes |
| `app/Filament/Admin/Resources/Users/Tables/UsersTable.php` | Users table |
| `tests/Concerns/WithAuthorizedUser.php` | Test helper for authenticated users |
| `tests/Feature/Filament/Users/UserResourceTest.php` | UserResource tests |
| `tests/Feature/Authorization/AuthorizationTest.php` | Authorization tests |

## Files modified (existing)

| File | Change |
|---|---|
| `app/Models/User.php` | Add `HasRoles` trait, `FilamentUser` contract, `canAccessPanel()`, `member()` relation |
| `app/Policies/InvoicePolicy.php` | Add `AuthorizesResource` trait, combine permission checks with existing status logic |
| `app/Filament/Admin/Resources/Members/Schemas/MemberForm.php` | Add `->visible()` and `->disabled()` to address/payment/registration tabs based on field-level permissions |
| `database/seeders/DatabaseSeeder.php` | Add `RolePermissionSeeder` call |
| `database/seeders/DevelopmentSeeder.php` | Assign roles to test users |
| `lang/nl/labels.php` | Add user/role labels with `role_names` array (English slugs → Dutch labels) |
| `lang/nl/notifications.php` | Add user notifications |
| `tests/Feature/Filament/StorageSpaces/StorageSpaceResourceTest.php` | Authenticate users in tests |
| `app/Providers/AppServiceProvider.php` | (Optional) Add `Gate::before` for super-admin |

## Open questions

1. **Relation manager permissions**: Relation managers on `MemberResource` (invoices, activities, storage rentals, outgoing emails) will check the related model's policy. Should `member_administration` have view access to all related data on a member, or only member-specific data?
2. **Super-admin**: Should a `super_admin` role be added for emergency access, or is `technical_administration` sufficient?
3. **Future member self-service**: When users are linked to members via `members.user_id`, policies will need an "own record" check. This is deferred until that linkage is implemented.
4. **Member field-level read-only vs hidden**: The current plan hides tabs entirely when the user lacks the view permission. An alternative is to show the tab but with all fields disabled (read-only). This would let users know the data exists without being able to see or edit it. Decide which UX is preferred.
