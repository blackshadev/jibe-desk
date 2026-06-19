# Roles and permissions approach

## Summary

### Recommended: use Laravel Policies + `spatie/laravel-permission`, and optionally add Filament Shield later
This is the best fit for the current codebase. Your app already uses Laravel + Filament resources, and Filament will automatically respect Laravel model policies for resource CRUD access. `spatie/laravel-permission` gives you durable role/permission storage, while policies enforce the real rules.

### Good alternative: self-build a small RBAC layer
If you want very tight control and your permission model will stay fairly small and stable, you can build your own `roles`, `permissions`, and pivot tables. This avoids package dependency, but you will end up re-creating much of what Spatie already solves.

### Not recommended except as a temporary phase: hardcoded roles only
You can hardcode role enums/checks in the `User` model and policies. It is fast to start, but becomes painful once roles change, admins need UI management, or permissions need to be audited.

---

## Solution 1 — Recommended: Laravel policies + Spatie permissions

### Why this is the best fit here

Your current structure already maps cleanly to role domains:

- **Technical** resources:
  - `app/Filament/Admin/Resources/OutgoingEmails/OutgoingEmailResource.php:18-55`
  - `app/Filament/Admin/Resources/MemberObjectTypes/MemberObjectTypeResource.php:22-65`
  - `app/Filament/Admin/Resources/ExtraMembershipItems/ExtraMembershipItemResource.php:22-73`
- **Member administration**:
  - `app/Filament/Admin/Resources/Members/MemberResource.php:29-92`
- **Financial administration**:
  - `app/Filament/Admin/Resources/Invoices/InvoiceResource.php:23-67`
  - `app/Filament/Admin/Resources/InvoiceBatches/InvoiceBatchResource.php:23-73`
- **Activities**:
  - `app/Filament/Admin/Resources/Activities/ActivityResource.php:23-74`
- **Rental / storage**:
  - `app/Filament/Admin/Resources/StorageSpaces/StorageSpaceResource.php:23-74`

Those domains even already exist in navigation as:

- `app/Filament/Admin/Navigation/NavigationGroup.php:10-23`

At the same time, the app currently has almost no reusable authorization foundation:

- `app/Models/User.php:20-42` has no roles, permissions, member relation, or panel access logic.
- `app/Policies/InvoicePolicy.php:11-37` is the only explicit policy found, and it currently allows any user to view/create invoices.
- `app/Filament/Admin/Resources/Members/MemberResource.php:51-63` exposes sensitive related data from the member area.
- `database/migrations/2026_05_14_134742_create_members_table.php:12-35` already contains the future ownership link via nullable unique `members.user_id`.

So the clean design is:

1. **Store roles/permissions on `users`**.
2. **Use policies for actual access checks**.
3. **Let Filament consume those policies automatically**.
4. **Add ownership rules for “my own member record only”**.
5. **Later attach each user to exactly one member through `members.user_id`**.

### Recommended role model

Use roles as coarse bundles, permissions as fine-grained abilities.

Suggested roles:

- `tech-admin`
- `member-admin`
- `financial-admin`
- `activity-admin`
- `storage-admin`
- optionally `super-admin`

Suggested permission families:

- `members.view-any`, `members.view-own`, `members.update-any`, `members.update-own`
- `outgoing-emails.view-any`
- `member-object-types.manage`
- `extra-membership-items.manage`
- `invoices.view-any`, `invoices.update`
- `invoice-batches.view-any`, `invoice-batches.manage`
- `activities.view-any`, `activities.manage`, `activities.assign-members`
- `storage-spaces.view-any`, `storage-spaces.manage`, `storage-spaces.assign-members`

That gives you a stable middle layer between business intent and Filament resources.

### How the authorization model should work

#### 1. Panel access

First decide who may enter `/admin` at all.

Today the panel is defined in `app/Providers/Filament/AdminPanelProvider.php:31-62`, but the `User` model does not implement explicit Filament panel access control in `app/Models/User.php:20-42`.

Recommended rule:

- Only users with at least one back-office role may access the admin panel.
- Ordinary members should use a future member-facing area, not the admin panel.

This matches Filament’s documented `canAccessPanel()` pattern.

#### 2. Resource-level authorization

For Filament resources, use Laravel policies per model. Filament 5 automatically checks model policies for standard resource CRUD actions.

That means:

- `MemberPolicy` controls `MemberResource`
- `InvoicePolicy` controls `InvoiceResource`
- `InvoiceBatchPolicy` controls `InvoiceBatchResource`
- `ActivityPolicy` controls `ActivityResource`
- `StorageSpacePolicy` controls `StorageSpaceResource`
- etc.

This is the correct layer for your business rules. Hiding navigation alone is not enough.

#### 3. Record-level ownership

Your default rule is: **a normal user can only access their own member record/details**.

You already have the right future database hook for this in:

- `database/migrations/2026_05_14_134742_create_members_table.php:14`

Because `members.user_id` is nullable and unique, the intended future model is effectively **one user ↔ one member**.

So your default policy rule should become:

- if user has admin permission: allow broader access
- otherwise only allow access where `member.user_id === user.id`

Later, when users are attached to members, this becomes straightforward and consistent.

#### 4. Relation manager / action permissions

Some of your important business actions happen in relation managers, not only in base resources:

- activities assignment via `app/Filament/Admin/Resources/Activities/ActivityResource.php:45-51`
- storage rentals via `app/Filament/Admin/Resources/StorageSpaces/StorageSpaceResource.php:45-50`
- member-related invoices / activities / storage / outgoing emails via `app/Filament/Admin/Resources/Members/MemberResource.php:51-63`

So do not stop at CRUD permissions like `viewAny` and `update`. Add explicit permissions for actions such as:

- assign members to activities
- assign members to storage rentals
- manage invoice batches

These can be enforced via policy methods or explicit gate/permission checks on custom Filament actions.

### Where this option is strongest

- Best long-term maintainability
- Works naturally with Laravel authorization and Filament resources
- Permissions can evolve without code rewrites everywhere
- Easy to add admin UI later
- Clean path for “own member record only” plus “staff/admin broader access”

### Risks / tradeoffs

- Slightly more setup than hardcoded roles
- You still need to design policies well; package alone does not solve business rules
- Member ownership logic cannot be fully enforced until users are linked to members

### Optional addition: Filament Shield

If you want a faster Filament admin experience for managing roles/permissions, add **Filament Shield** on top of Spatie.

That package is useful because it:

- integrates with Filament
- helps generate policies/permissions
- provides UI for roles and permissions
- supports custom permissions for non-standard actions

I would treat Shield as an **optional admin convenience layer**, not as the foundation. The foundation should still be Laravel policies + stored permissions.

---

## Solution 2 — Self-build a lightweight RBAC system

### When this is a good choice

Choose this only if:

- your roles are unlikely to change much
- you want complete control over schema and naming
- you prefer no external package dependency

### What you would build

Typically:

- `roles`
- `permissions`
- `role_user`
- `permission_role` or `permission_user`

Then:

- add `User -> roles()`
- add helper methods like `hasRole()` / `hasPermission()`
- use those inside Laravel policies

### Why it can work in this codebase

This codebase is still early in its authz maturity:

- no roles package is installed
- no broad policy system exists yet
- user/member linkage is not fully active yet

So technically, this is a reasonable time to build your own model if you are sure the scope stays modest.

### Why I still would not recommend it first

The moment you need:

- audited permission changes
- admin UI to assign roles
- seeding standard roles
- caching / performance handling
- multiple permission styles

you will start rebuilding what `spatie/laravel-permission` already gives you.

For that reason, self-build is viable, but mostly attractive only if you deliberately want a very small, internal-only authorization layer.

---

## Solution 3 — Hardcoded roles in code

### What this looks like

You store a simple role flag or enum on `users`, then policies do checks like:

- tech admins may access technical resources
- financial admins may access invoicing resources
- normal users may only access their own member

### Why it is tempting

- fastest first implementation
- easy to reason about initially
- minimal database complexity

### Why I do not recommend it here

Your requested access model is already beyond “just one admin role”:

- technical area
- member administration
- financial administration
- activity administration
- storage administration
- default self-service ownership

That is already a real RBAC system. Hardcoding it will become rigid quickly.

Use this only as a temporary bootstrap phase if you need something working immediately before introducing proper permissions.

---

## Recommended design for this exact application

### 1. Separate back-office access from self-service access

Do **not** put ordinary members in the same unrestricted Filament admin panel.

Recommended split:

- **Admin/back-office users**: access Filament `/admin`
- **Regular users/members**: access only their own member data through a separate member-facing area later

Reason: your current Filament resources expose broad operational data. For example, the member resource includes relation managers for invoices, activities, member objects, storage rentals, and outgoing emails in `app/Filament/Admin/Resources/Members/MemberResource.php:51-63`.

### 2. Make policies the source of truth

Use permissions to answer **“what kind of actor is this?”** and policies to answer **“may they do this here, on this record?”**.

For example:

- role grants permission family
- policy decides own-record vs any-record
- policy also decides status-based rules, like your existing invoice update logic in `app/Policies/InvoicePolicy.php:28-35`

### 3. Attach users to members as soon as that feature is ready

The app is already prepared for it at schema level:

- `database/migrations/2026_05_14_134742_create_members_table.php:14-15,35`

Once that relation is active, your baseline authorization rule becomes simple and safe.

### 4. Model permissions around business actions, not only pages

Do not only think in terms of menu items such as “can see Activities”.

Also define permissions like:

- assign member to activity
- assign member to storage
- manage invoice batch
- manage extra membership items

This matters because UI screens and relation managers often bundle several business capabilities together.

### 5. Use a super-admin override sparingly

Laravel supports a global authorization override via `Gate::before(...)`. That is good for one or two platform owners, but not as a substitute for proper permissions.

---

## Practical recommendation

If I were implementing this in this project, I would choose:

1. **`spatie/laravel-permission` as the RBAC storage layer**
2. **Laravel policies as the enforcement layer**
3. **Filament policy integration for resources/pages/actions**
4. **Optional Filament Shield later for management UI and permission generation**
5. **User-to-member ownership checks once `members.user_id` is actively used**

That gives you:

- a clean default rule for normal users
- distinct staff/admin roles
- future flexibility when more domains are added
- strong fit with the codebase you already have

---

## Built-in framework features you can rely on

- **Laravel Fortify** for authentication only, already present in `config/fortify.php:20-158`
- **Laravel Gates and Policies** for authorization
- **Filament resource authorization via Laravel policies**
- **Filament panel access control** via `canAccessPanel()` on the user model
- **Custom Filament resource query scoping** where needed for “own record only” behavior

So: authentication is already there, but authorization still needs to be designed.

---

## Sources

### Codebase sources

- `app/Models/User.php:18-42`
- `app/Models/Member.php:31-105`
- `app/Providers/Filament/AdminPanelProvider.php:31-62`
- `app/Policies/InvoicePolicy.php:11-37`
- `app/Filament/Admin/Navigation/NavigationGroup.php:10-23`
- `app/Filament/Admin/Resources/Members/MemberResource.php:29-92`
- `app/Filament/Admin/Resources/OutgoingEmails/OutgoingEmailResource.php:18-55`
- `app/Filament/Admin/Resources/MemberObjectTypes/MemberObjectTypeResource.php:22-65`
- `app/Filament/Admin/Resources/ExtraMembershipItems/ExtraMembershipItemResource.php:22-73`
- `app/Filament/Admin/Resources/Invoices/InvoiceResource.php:23-67`
- `app/Filament/Admin/Resources/InvoiceBatches/InvoiceBatchResource.php:23-73`
- `app/Filament/Admin/Resources/Activities/ActivityResource.php:23-74`
- `app/Filament/Admin/Resources/StorageSpaces/StorageSpaceResource.php:23-74`
- `config/auth.php:20-117`
- `config/fortify.php:20-158`
- `database/migrations/2026_05_14_134742_create_members_table.php:12-35`

### External sources

- Laravel Authorization docs — https://laravel.com/docs/13.x/authorization
- Laravel Fortify docs — https://laravel.com/docs/13.x/fortify
- Filament panel access docs — https://filamentphp.com/docs/5.x/panels/users#authorizing-access-to-the-panel
- Filament security / authorization docs — https://filamentphp.com/docs/5.x/panels/security#authorization
- Filament resource query docs — https://filamentphp.com/docs/5.x/resources/overview#customizing-the-resource-eloquent-query
- Spatie Laravel Permission docs — https://spatie.be/docs/laravel-permission/v6/introduction
- Filament Shield repository/docs — https://github.com/bezhanSalleh/filament-shield
