# Role-specific staff dashboard widgets

Date: 2026-10-20
Status: ready for implementation
Depends on: `20261020_dashboard-role-aware-widget-foundation.md`

## Overview

This plan adds one dashboard card for each non-financial staff role except member administration:

- **Activity administration** — upcoming activities and their total participants.
- **Rental administration** — rentals ending within 30 days, and vacant storage spaces.
- **Technical administration** — failed outgoing emails.

Member administration intentionally gets no card: `MemberOverview` already shows the pending-member count as its subtitle. Financial administration keeps its widgets from `20261020_financial-dashboard-widgets.md`, including the inventory write-off card, because inventory permissions already belong to that role.

## Current situation

- `Activity` has an `active` scope (`end_date` null or future) and a `members` belongs-to-many relation. The scope uses a top-level `orWhere`, so it must be wrapped in a nested closure.
- `StorageSpace` has an `available` scope (`whereDoesntHave('currentRental')`) and `StorageSpaceRental` stores `start_date` and nullable `end_date`.
- `OutgoingEmail` casts `status` to `OutgoingEmailStatus`, which has `Failed`.
- `RoleName` exposes `ActivityAdministration`, `RentalAdministration`, and `TechnicalAdministration`.
- `ListStorageSpaces` already has an `available` tab, but this plan does not add drill-down; only the two required financial widgets link to filtered lists.

## Planned changes

- One widget for activity administration.
- Two widgets for rental administration.
- One widget for technical administration.
- Dutch labels.
- Role-gating and content tests.

## Upcoming activities widget

Create `app/Filament/Admin/Widgets/Dashboard/UpcomingActivitiesOverviewWidget.php`:

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets\Dashboard;

use App\Domain\Authorization\RoleName;
use App\Filament\Admin\Widgets\Dashboard\Concerns\VisibleForRoles;
use App\Models\Activity;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Override;

final class UpcomingActivitiesOverviewWidget extends StatsOverviewWidget
{
    use VisibleForRoles;

    #[Override]
    protected static ?int $sort = 30;

    #[Override]
    protected ?string $pollingInterval = null;

    /**
     * @return list<RoleName>
     */
    protected static function roles(): array
    {
        return [RoleName::ActivityAdministration];
    }

    #[Override]
    protected function getStats(): array
    {
        $activities = Activity::query()
            ->where(static fn (Builder $query): Builder => $query->active())
            ->withCount('members')
            ->get();

        $participantCount = (int) $activities->sum('members_count');

        return [
            Stat::make(__('labels.upcoming_activities'), (string) $activities->count())
                ->description(trans_choice('labels.participant_count', $participantCount, ['count' => $participantCount]))
                ->color('primary'),
        ];
    }
}
```

This card is club-wide, matching the agreed split: staff see every upcoming activity, members only the ones they joined.

## Expiring rentals widget

Create `app/Filament/Admin/Widgets/Dashboard/ExpiringRentalsWidget.php`:

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets\Dashboard;

use App\Domain\Authorization\RoleName;
use App\Filament\Admin\Widgets\Dashboard\Concerns\VisibleForRoles;
use App\Models\StorageSpaceRental;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Override;

final class ExpiringRentalsWidget extends StatsOverviewWidget
{
    use VisibleForRoles;

    #[Override]
    protected static ?int $sort = 31;

    #[Override]
    protected ?string $pollingInterval = null;

    /**
     * @return list<RoleName>
     */
    protected static function roles(): array
    {
        return [RoleName::RentalAdministration];
    }

    #[Override]
    protected function getStats(): array
    {
        $today = CarbonImmutable::today();

        $count = StorageSpaceRental::query()
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [$today, $today->addDays(30)])
            ->count();

        return [
            Stat::make(__('labels.expiring_rentals'), (string) $count)
                ->description(__('labels.expiring_rentals_description'))
                ->color($count > 0 ? 'warning' : 'success'),
        ];
    }
}
```

Rentals without an `end_date` are open-ended and intentionally not counted.

## Vacant storage spaces widget

Create `app/Filament/Admin/Widgets/Dashboard/VacantStorageSpacesWidget.php`:

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets\Dashboard;

use App\Domain\Authorization\RoleName;
use App\Filament\Admin\Widgets\Dashboard\Concerns\VisibleForRoles;
use App\Models\StorageSpace;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Override;

final class VacantStorageSpacesWidget extends StatsOverviewWidget
{
    use VisibleForRoles;

    #[Override]
    protected static ?int $sort = 32;

    #[Override]
    protected ?string $pollingInterval = null;

    /**
     * @return list<RoleName>
     */
    protected static function roles(): array
    {
        return [RoleName::RentalAdministration];
    }

    #[Override]
    protected function getStats(): array
    {
        return [
            Stat::make(
                __('labels.vacant_storage_spaces'),
                (string) StorageSpace::query()->available()->count(),
            )
                ->description(__('labels.vacant_storage_spaces_description'))
                ->color('primary'),
        ];
    }
}
```

## Failed outgoing emails widget

Create `app/Filament/Admin/Widgets/Dashboard/FailedOutgoingEmailsWidget.php`:

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets\Dashboard;

use App\Domain\Authorization\RoleName;
use App\Domain\Mail\OutgoingEmailStatus;
use App\Filament\Admin\Widgets\Dashboard\Concerns\VisibleForRoles;
use App\Models\OutgoingEmail;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Override;

final class FailedOutgoingEmailsWidget extends StatsOverviewWidget
{
    use VisibleForRoles;

    #[Override]
    protected static ?int $sort = 33;

    #[Override]
    protected ?string $pollingInterval = null;

    /**
     * @return list<RoleName>
     */
    protected static function roles(): array
    {
        return [RoleName::TechnicalAdministration];
    }

    #[Override]
    protected function getStats(): array
    {
        $count = OutgoingEmail::query()
            ->where('status', OutgoingEmailStatus::Failed)
            ->count();

        return [
            Stat::make(__('labels.failed_emails'), (string) $count)
                ->description(__('labels.failed_emails_description'))
                ->color($count > 0 ? 'danger' : 'success'),
        ];
    }
}
```

## Registering the widgets

Append the four classes to `DashboardWidgets::all()`:

```php
UpcomingActivitiesOverviewWidget::class,
ExpiringRentalsWidget::class,
VacantStorageSpacesWidget::class,
FailedOutgoingEmailsWidget::class,
```

## Labels

Add to `lang/nl/labels.php`:

```php
'upcoming_activities' => 'Aankomende activiteiten',
'participant_count' => '{1} :count deelnemer|[2,*] :count deelnemers',
'expiring_rentals' => 'Huurcontracten die aflopen',
'expiring_rentals_description' => 'Binnen 30 dagen',
'vacant_storage_spaces' => 'Beschikbare opslagruimtes',
'vacant_storage_spaces_description' => 'Zonder lopende verhuur',
'failed_emails' => 'Mislukte e-mails',
'failed_emails_description' => 'Controleer het verzendlog',
```

`labels.start_date`, `labels.end_date`, and `labels.available` already exist and are not redefined.

## Tests

Create `tests/Feature/Filament/Dashboard/RoleWidgetsTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Dashboard;

use App\Domain\Authorization\RoleName;
use App\Domain\Mail\OutgoingEmailStatus;
use App\Filament\Admin\Widgets\Dashboard\ExpiringRentalsWidget;
use App\Filament\Admin\Widgets\Dashboard\FailedOutgoingEmailsWidget;
use App\Filament\Admin\Widgets\Dashboard\UpcomingActivitiesOverviewWidget;
use App\Filament\Admin\Widgets\Dashboard\VacantStorageSpacesWidget;
use App\Models\Activity;
use App\Models\OutgoingEmail;
use App\Models\StorageSpace;
use App\Models\StorageSpaceRental;
use Livewire\Livewire;
use Tests\Concerns\WithAuthorizedUser;
use Tests\FeatureTestCase;

final class RoleWidgetsTest extends FeatureTestCase
{
    use WithAuthorizedUser;

    public function test_upcoming_activities_widget_counts_active_activities_and_participants(): void
    {
        $this->withUserHavingRole(RoleName::ActivityAdministration);

        $first = Activity::factory()->createQuietly(['end_date' => now()->addMonth()]);
        $second = Activity::factory()->createQuietly(['end_date' => now()->addMonths(2)]);
        Activity::factory()->createQuietly(['end_date' => now()->subDay()]);

        $first->members()->attach(
            \App\Models\Member::factory()->count(2)->createQuietly()->modelKeys(),
        );
        $second->members()->attach(
            \App\Models\Member::factory()->createQuietly()->getKey(),
        );

        Livewire::test(UpcomingActivitiesOverviewWidget::class)
            ->assertSee('2')
            ->assertSee(trans_choice('labels.participant_count', 3, ['count' => 3]));
    }

    public function test_expiring_rentals_widget_only_counts_the_next_thirty_days(): void
    {
        $this->withUserHavingRole(RoleName::RentalAdministration);

        StorageSpaceRental::factory()->createQuietly(['end_date' => now()->addDays(10)]);
        StorageSpaceRental::factory()->createQuietly(['end_date' => now()->addDays(60)]);
        StorageSpaceRental::factory()->createQuietly(['end_date' => null]);

        Livewire::test(ExpiringRentalsWidget::class)
            ->assertSee('1');
    }

    public function test_vacant_storage_spaces_widget_counts_spaces_without_a_rental(): void
    {
        $this->withUserHavingRole(RoleName::RentalAdministration);

        StorageSpace::factory()->createQuietly();
        $rented = StorageSpace::factory()->createQuietly();

        StorageSpaceRental::factory()->createQuietly([
            'storage_space_id' => $rented->id,
            'start_date' => now()->subMonth(),
            'end_date' => null,
        ]);

        Livewire::test(VacantStorageSpacesWidget::class)
            ->assertSee('1');
    }

    public function test_failed_emails_widget_counts_failed_outgoing_emails(): void
    {
        $this->withUserHavingRole(RoleName::TechnicalAdministration);

        OutgoingEmail::factory()->count(2)->createQuietly(['status' => OutgoingEmailStatus::Failed]);
        OutgoingEmail::factory()->createQuietly(['status' => OutgoingEmailStatus::Sent]);

        Livewire::test(FailedOutgoingEmailsWidget::class)
            ->assertSee('2');
    }

    public function test_widgets_are_hidden_from_other_roles(): void
    {
        $this->withUserHavingRole(RoleName::MemberAdministration);

        static::assertFalse(UpcomingActivitiesOverviewWidget::canView());
        static::assertFalse(ExpiringRentalsWidget::canView());
        static::assertFalse(VacantStorageSpacesWidget::canView());
        static::assertFalse(FailedOutgoingEmailsWidget::canView());
    }
}
```

The `attach()` calls accept a collection; when a collection is passed, Eloquent attaches each model. Use `$first->members()->attach($members->modelKeys())` if the collection form gives trouble.

Run with:

```bash
./Taskfile artisan test --compact tests/Feature/Filament/Dashboard/RoleWidgetsTest.php
```
