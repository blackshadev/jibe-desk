# Dashboard role-aware widget foundation

Date: 2026-10-20
Status: ready for implementation
Related plans:

- `20261020_financial-dashboard-widgets.md`
- `20261020_member-dashboard-widgets.md`
- `20261020_role-specific-staff-dashboard-widgets.md`

## Overview

This plan introduces the composition seam that the other three dashboard plans build on: one registry that lists every dashboard widget, and one reusable `canView()` gate that hides a widget from users who do not hold the required role.

The dashboard keeps being a single page. Filament already filters widgets through `canView()` before mounting them, so the page itself does not need per-role branches. `MemberOverview` stays visible to everyone, including logged-in members, as agreed in the discovery session.

## Current situation

- `app/Filament/Admin/Pages/Dashboard.php` overrides `getWidgets()` with a hardcoded list: `MemberOverview` for everyone and `HouseholdMembersWidget` only when `auth()->user()->isMember()`.
- No widget implements `canView()`.
- Widgets are discovered by `AdminPanelProvider` via `->discoverWidgets(in: app_path('Filament/Admin/Widgets'), ...)`, but the dashboard override means only the hardcoded two are rendered.
- `tests/Feature/Filament/Admin/Pages/MemberPortalTest.php` asserts the exact `getWidgets()` array, so it will need updating.
- Role names live in `App\Domain\Authorization\RoleName` and `User::hasAnyRole()` (Spatie) accepts an array of role name strings.

## Planned changes

- Add `App\Filament\Admin\Widgets\Dashboard\DashboardWidgets` as the single registry of dashboard widgets.
- Add the `VisibleForRoles` trait for widget-level role gating.
- Make `Dashboard::getWidgets()` delegate to the registry.
- Gate `HouseholdMembersWidget` on `isMember()`.
- Keep `MemberOverview` ungated.
- Add a focused visibility test and update the existing member portal test.

## Widget registry

Create `app/Filament/Admin/Widgets/Dashboard/DashboardWidgets.php`:

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets\Dashboard;

use Filament\Widgets\Widget;

final class DashboardWidgets
{
    /**
     * @return list<class-string<Widget>>
     */
    public static function all(): array
    {
        return [
            MemberOverview::class,
            HouseholdMembersWidget::class,
        ];
    }
}
```

The later plans append their widget classes to this list. Ordering inside the dashboard is controlled by each widget's `$sort` property, not by this array.

## Role gate trait

Create `app/Filament/Admin/Widgets/Dashboard/Concerns/VisibleForRoles.php`:

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets\Dashboard\Concerns;

use App\Domain\Authorization\RoleName;

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
```

A user holding several roles sees the union of the widgets for those roles. This matches the existing cluster gating and needs no permission changes.

## Dashboard composition

Update `app/Filament/Admin/Pages/Dashboard.php` so the page no longer inspects the user:

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Widgets\Dashboard\DashboardWidgets;
use Filament\Pages\Dashboard as BaseDashboard;
use Override;

final class Dashboard extends BaseDashboard
{
    #[Override]
    protected static string $routePath = 'dashboard';

    #[Override]
    protected static ?int $navigationSort = -10;

    #[Override]
    public function getWidgets(): array
    {
        return DashboardWidgets::all();
    }
}
```

Filament filters the returned classes through each widget's `canView()` before rendering. Removing the inline member branch means the page does not grow a new `if` for every future role.

## Existing widget gates

`MemberOverview` stays as it is; it has no `canView()` override and therefore remains visible to every authenticated user, including members.

`HouseholdMembersWidget` replaces its membership in the conditional with a static gate:

```php
public static function canView(): bool
{
    return auth()->user()?->isMember() ?? false;
}
```

No other changes are required in `HouseholdMembersWidget`; its table query is already scoped through `visibleMemberIds()`.

## Tests

Create `tests/Feature/Filament/Admin/Pages/DashboardWidgetVisibilityTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Admin\Pages;

use App\Domain\Authorization\RoleName;
use App\Filament\Admin\Widgets\Dashboard\DashboardWidgets;
use App\Filament\Admin\Widgets\Dashboard\HouseholdMembersWidget;
use App\Filament\Admin\Widgets\Dashboard\MemberOverview;
use App\Models\User;
use Tests\Concerns\WithAuthorizedUser;
use Tests\FeatureTestCase;

final class DashboardWidgetVisibilityTest extends FeatureTestCase
{
    use WithAuthorizedUser;

    public function test_registry_contains_the_existing_dashboard_widgets(): void
    {
        static::assertContains(MemberOverview::class, DashboardWidgets::all());
        static::assertContains(HouseholdMembersWidget::class, DashboardWidgets::all());
    }

    public function test_member_sees_member_overview_and_household_members(): void
    {
        $this->actingAs(User::factory()->memberUser()->createQuietly());

        static::assertTrue(MemberOverview::canView());
        static::assertTrue(HouseholdMembersWidget::canView());
    }

    public function test_staff_sees_member_overview_but_not_household_members(): void
    {
        $this->withUserHavingRole(RoleName::FinancialAdministration);

        static::assertTrue(MemberOverview::canView());
        static::assertFalse(HouseholdMembersWidget::canView());
    }

    public function test_guest_cannot_view_any_dashboard_widget(): void
    {
        static::assertFalse(HouseholdMembersWidget::canView());
    }
}
```

Update `tests/Feature/Filament/Admin/Pages/MemberPortalTest.php`:

- Replace `test_member_dashboard_shows_only_the_household_members_widget` with an assertion that the member sees both existing widgets through `canView()`.
- Replace `test_admin_dashboard_shows_only_the_member_overview_widget` with an assertion that a member-administration user sees `MemberOverview` and not `HouseholdMembersWidget`.
- Keep the `HouseholdMembersWidget` scoping test and the Fortify redirect test unchanged.

Run the focused tests with:

```bash
./Taskfile artisan test --compact --filter=DashboardWidgetVisibility
./Taskfile artisan test --compact tests/Feature/Filament/Admin/Pages/MemberPortalTest.php
```

## Conventions for the follow-up plans

- Every new staff widget uses `VisibleForRoles` and declares `protected static function roles(): array`.
- Every new member widget gates on `auth()->user()?->isMember()`.
- New widgets are appended to `DashboardWidgets::all()`.
- `$sort` controls ordering: the two existing widgets keep their default (`0`); financial widgets use `10`–`15`, member widgets `20`–`23`, and other role cards `30`–`33`.
- Stats widgets disable polling with `protected ?string $pollingInterval = null;` because their queries are heavier than the default 5-second refresh warrants.
