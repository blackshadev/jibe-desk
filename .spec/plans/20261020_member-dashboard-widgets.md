# Member dashboard widgets

Date: 2026-10-20
Status: ready for implementation
Depends on: `20261020_dashboard-role-aware-widget-foundation.md`

## Overview

This plan gives logged-in members a dashboard of their own, alongside the existing `MemberOverview` and `HouseholdMembersWidget`:

- **My open invoices** — a table of Open/Pending invoices for the member's household, linking to `ViewInvoice`.
- **My upcoming activities** — activities the member or their household joined that have not ended.
- **My storage rentals** — current storage space rentals with their end date.
- **Membership card** — membership name, household name and size, volunteer flag, and login account roles.

`MemberOverview` deliberately stays visible to members, as agreed. Staff never see these four widgets.

## Current situation

- `Member::visibleMemberIds()` returns the member's own id plus every member in the same household, and is already used by `HouseholdMembersWidget`.
- Members can open `ViewInvoice` because `ViewInvoice::authorizeResourceAccess()` allows `isMember()` in addition to resource access. There is no member invoice list today.
- `Member` exposes `membership`, `household`, `householdMembers`, and an `activities` belongs-to-many relation. `Household` has no name column; `Household::member_names` composes one from its members.
- `Activity` has an `active` scope (`end_date` null or in the future). The scope uses a top-level `orWhere`, so it must be wrapped in a nested closure whenever it is combined with `whereHas`.
- `StorageSpaceRental` has `start_date`, nullable `end_date`, and a `storageSpace` relation; `StorageSpace` has `location` and `number`.
- `User` uses Spatie's `HasRoles`, so `getRoleNames()` returns the login account's roles. Member accounts normally have none.
- Custom widget views live under `resources/views/filament/admin/...`; see `resources/views/filament/admin/resources/sepa-batches/widgets/`.

## Planned changes

- Three table widgets and one custom widget under `app/Filament/Admin/Widgets/Dashboard/`.
- One Blade view for the membership card.
- Dutch labels.
- Tests for household scoping, activity scoping, rental scoping, and card content.

## My open invoices widget

Create `app/Filament/Admin/Widgets/Dashboard/MyOpenInvoicesWidget.php`:

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets\Dashboard;

use App\Domain\Billing\Invoices\InvoiceStatus;
use App\Domain\SharedKernel\CompoundPrice;
use App\Filament\Admin\Resources\Invoices\InvoiceResource;
use App\Models\Invoice;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Override;

final class MyOpenInvoicesWidget extends TableWidget
{
    #[Override]
    protected static ?int $sort = 20;

    public static function canView(): bool
    {
        return auth()->user()?->isMember() ?? false;
    }

    #[Override]
    public function table(Table $table): Table
    {
        return $table
            ->heading(__('labels.my_open_invoices'))
            ->query(static fn (): Builder => Invoice::query()
                ->openOrPending()
                ->whereIn('member_id', auth()->user()?->member?->visibleMemberIds() ?? [])
                ->orderBy('date'))
            ->columns([
                TextColumn::make('date')
                    ->label(__('labels.invoice_date'))
                    ->date(),
                TextColumn::make('invoice_number')
                    ->label(__('labels.invoice_number')),
                TextColumn::make('status')
                    ->label(__('labels.status'))
                    ->formatStateUsing(static fn (InvoiceStatus $state): string => __('labels.invoice_status.' . $state->value)),
                TextColumn::make('total')
                    ->label(__('labels.total'))
                    ->formatStateUsing(static fn (CompoundPrice $state): string => (string) $state)
                    ->alignEnd(),
            ])
            ->recordUrl(
                static fn (Invoice $record): string => InvoiceResource::getUrl('view', ['record' => $record]),
            )
            ->emptyStateHeading(__('labels.no_open_invoices'))
            ->paginated(false);
    }
}
```

The query is scoped to `visibleMemberIds()`, so a member never sees another household's invoices. The record URL is safe because `ViewInvoice` explicitly allows member access.

## My upcoming activities widget

Create `app/Filament/Admin/Widgets/Dashboard/MyUpcomingActivitiesWidget.php`:

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets\Dashboard;

use App\Models\Activity;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Override;

final class MyUpcomingActivitiesWidget extends TableWidget
{
    #[Override]
    protected static ?int $sort = 21;

    public static function canView(): bool
    {
        return auth()->user()?->isMember() ?? false;
    }

    #[Override]
    public function table(Table $table): Table
    {
        return $table
            ->heading(__('labels.my_upcoming_activities'))
            ->query(static fn (): Builder => Activity::query()
                ->where(static fn (Builder $query): Builder => $query->active())
                ->whereHas(
                    'members',
                    static fn (Builder $query): Builder => $query
                        ->whereIn('members.id', auth()->user()?->member?->visibleMemberIds() ?? []),
                )
                ->orderBy('start_date'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('labels.name')),
                TextColumn::make('start_date')
                    ->label(__('labels.start_date'))
                    ->date(),
                TextColumn::make('end_date')
                    ->label(__('labels.end_date'))
                    ->date()
                    ->placeholder('—'),
            ])
            ->emptyStateHeading(__('labels.no_upcoming_activities'))
            ->paginated(false);
    }
}
```

The `active()` scope is wrapped in a nested closure so its top-level `orWhere` cannot escape the `whereHas` constraint. Household members are included because members usually join activities as a family.

## My storage rentals widget

Create `app/Filament/Admin/Widgets/Dashboard/MyStorageRentalsWidget.php`:

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets\Dashboard;

use App\Models\StorageSpaceRental;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Override;

final class MyStorageRentalsWidget extends TableWidget
{
    #[Override]
    protected static ?int $sort = 22;

    public static function canView(): bool
    {
        return auth()->user()?->isMember() ?? false;
    }

    #[Override]
    public function table(Table $table): Table
    {
        return $table
            ->heading(__('labels.my_storage_rentals'))
            ->query(static fn (): Builder => StorageSpaceRental::query()
                ->whereIn('member_id', auth()->user()?->member?->visibleMemberIds() ?? [])
                ->whereNowOrPast('start_date')
                ->where(static fn (Builder $query): Builder => $query
                    ->whereNull('end_date')
                    ->orWhereFuture('end_date'))
                ->with('storageSpace.location')
                ->orderBy('end_date'))
            ->columns([
                TextColumn::make('storageSpace.location.name')
                    ->label(__('labels.location')),
                TextColumn::make('storageSpace.number')
                    ->label(__('labels.space_number')),
                TextColumn::make('start_date')
                    ->label(__('labels.start_date'))
                    ->date(),
                TextColumn::make('end_date')
                    ->label(__('labels.rented_until'))
                    ->date()
                    ->placeholder(__('labels.no_end_date')),
            ])
            ->emptyStateHeading(__('labels.no_storage_rentals'))
            ->paginated(false);
    }
}
```

## Membership card widget

Create `app/Filament/Admin/Widgets/Dashboard/MembershipCardWidget.php`:

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets\Dashboard;

use Filament\Widgets\Widget;
use Override;

final class MembershipCardWidget extends Widget
{
    #[Override]
    protected static ?int $sort = 23;

    #[Override]
    protected int|string|array $columnSpan = 1;

    #[Override]
    protected string $view = 'filament.admin.widgets.dashboard.membership-card';

    public static function canView(): bool
    {
        return auth()->user()?->isMember() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    protected function getViewData(): array
    {
        $user = auth()->user();
        $member = $user?->member;
        $household = $member?->household;

        return [
            'memberName' => $member?->name,
            'membershipName' => $member?->membership?->name,
            'householdName' => $household?->member_names,
            'householdSize' => $household?->members->count() ?? 0,
            'isVolunteer' => (bool) ($member?->is_volunteer ?? false),
            'roles' => $member?->user?->getRoleNames()->all() ?? [],
        ];
    }
}
```

Create `resources/views/filament/admin/widgets/dashboard/membership-card.blade.php`:

```blade
<x-filament-widgets::widget>
    <x-filament::section :heading="__('labels.membership_card')">
        <dl class="grid gap-3 text-sm">
            <div class="flex justify-between gap-4">
                <dt class="text-gray-500 dark:text-gray-400">{{ __('labels.member') }}</dt>
                <dd class="font-medium text-gray-950 dark:text-white">{{ $memberName }}</dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="text-gray-500 dark:text-gray-400">{{ __('labels.membership') }}</dt>
                <dd class="font-medium text-gray-950 dark:text-white">{{ $membershipName ?? '—' }}</dd>
            </div>
            @if ($householdName !== null)
                <div class="flex justify-between gap-4">
                    <dt class="text-gray-500 dark:text-gray-400">{{ __('labels.household') }}</dt>
                    <dd class="font-medium text-gray-950 dark:text-white">
                        {{ $householdName }}
                        <span class="text-gray-500 dark:text-gray-400">({{ trans_choice('labels.household_member_count', $householdSize, ['count' => $householdSize]) }})</span>
                    </dd>
                </div>
            @endif
            <div class="flex justify-between gap-4">
                <dt class="text-gray-500 dark:text-gray-400">{{ __('labels.volunteer') }}</dt>
                <dd class="font-medium text-gray-950 dark:text-white">
                    {{ $isVolunteer ? __('labels.yes') : __('labels.no') }}
                </dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="text-gray-500 dark:text-gray-400">{{ __('labels.roles') }}</dt>
                <dd class="font-medium text-gray-950 dark:text-white">
                    {{ $roles === [] ? '—' : implode(', ', $roles) }}
                </dd>
            </div>
        </dl>
    </x-filament::section>
</x-filament-widgets::widget>
```

Because member accounts normally carry no Spatie role, the roles row renders `—`. This is expected and was confirmed during discovery.

## Registering the widgets

Append the four classes to `DashboardWidgets::all()`:

```php
MemberOverview::class,
HouseholdMembersWidget::class,
MyOpenInvoicesWidget::class,
MyUpcomingActivitiesWidget::class,
MyStorageRentalsWidget::class,
MembershipCardWidget::class,
```

`HouseholdMembersWidget` keeps its default sort; the new member widgets use `20`–`23` so they sit below the existing overview and household table.

## Labels

Add to `lang/nl/labels.php`:

```php
'my_open_invoices' => 'Mijn openstaande facturen',
'my_upcoming_activities' => 'Mijn aankomende activiteiten',
'my_storage_rentals' => 'Mijn opslagruimtes',
'membership_card' => 'Mijn lidmaatschap',
'volunteer' => 'Vrijwilliger',
'yes' => 'Ja',
'no' => 'Nee',
'no_open_invoices' => 'Geen openstaande facturen',
'no_upcoming_activities' => 'Geen aankomende activiteiten',
'no_storage_rentals' => 'Geen lopende opslagruimtes',
'no_end_date' => 'Onbepaalde tijd',
'household_member_count' => '{1} :count lid|[2,*] :count leden',
```

Check for collisions first: `labels.roles`, `labels.household`, `labels.member`, `labels.membership`, `labels.location`, `labels.space_number`, `labels.start_date`, `labels.end_date`, and `labels.rented_until` already exist.

## Tests

Create `tests/Feature/Filament/Dashboard/MemberWidgetsTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Dashboard;

use App\Domain\Billing\Invoices\InvoiceStatus;
use App\Filament\Admin\Widgets\Dashboard\MembershipCardWidget;
use App\Filament\Admin\Widgets\Dashboard\MyOpenInvoicesWidget;
use App\Filament\Admin\Widgets\Dashboard\MyStorageRentalsWidget;
use App\Filament\Admin\Widgets\Dashboard\MyUpcomingActivitiesWidget;
use App\Models\Activity;
use App\Models\Household;
use App\Models\Invoice;
use App\Models\StorageSpaceRental;
use App\Models\User;
use Livewire\Livewire;
use Tests\FeatureTestCase;

final class MemberWidgetsTest extends FeatureTestCase
{
    public function test_member_sees_own_and_household_open_invoices_only(): void
    {
        $user = User::factory()->memberUser()->createQuietly();
        $this->actingAs($user);

        $household = Household::factory()->createQuietly();
        $user->member->updateQuietly(['household_id' => $household->id]);

        $own = Invoice::factory()->forMember($user->member)->createQuietly(['status' => InvoiceStatus::Open]);
        $householdInvoice = Invoice::factory()
            ->forMember(\App\Models\Member::factory()->inHousehold($household)->createQuietly())
            ->createQuietly(['status' => InvoiceStatus::Pending]);
        $other = Invoice::factory()->createQuietly(['status' => InvoiceStatus::Open]);
        $paid = Invoice::factory()->forMember($user->member)->createQuietly(['status' => InvoiceStatus::Paid]);

        Livewire::test(MyOpenInvoicesWidget::class)
            ->assertCanSeeTableRecords([$own, $householdInvoice])
            ->assertCanNotSeeTableRecords([$other, $paid]);
    }

    public function test_member_sees_joined_upcoming_activities_only(): void
    {
        $user = User::factory()->memberUser()->createQuietly();
        $this->actingAs($user);

        $joined = Activity::factory()->createQuietly(['end_date' => now()->addMonth()]);
        $joined->members()->attach($user->member);

        $notJoined = Activity::factory()->createQuietly(['end_date' => now()->addMonth()]);
        $ended = Activity::factory()->createQuietly(['end_date' => now()->subDay()]);
        $ended->members()->attach($user->member);

        Livewire::test(MyUpcomingActivitiesWidget::class)
            ->assertCanSeeTableRecords([$joined])
            ->assertCanNotSeeTableRecords([$notJoined, $ended]);
    }

    public function test_member_sees_only_current_storage_rentals(): void
    {
        $user = User::factory()->memberUser()->createQuietly();
        $this->actingAs($user);

        $current = StorageSpaceRental::factory()->createQuietly([
            'member_id' => $user->member->id,
            'start_date' => now()->subMonth(),
            'end_date' => null,
        ]);
        $ended = StorageSpaceRental::factory()->createQuietly([
            'member_id' => $user->member->id,
            'start_date' => now()->subYear(),
            'end_date' => now()->subMonth(),
        ]);
        $future = StorageSpaceRental::factory()->createQuietly([
            'member_id' => $user->member->id,
            'start_date' => now()->addMonth(),
            'end_date' => null,
        ]);

        Livewire::test(MyStorageRentalsWidget::class)
            ->assertCanSeeTableRecords([$current])
            ->assertCanNotSeeTableRecords([$ended, $future]);
    }

    public function test_membership_card_renders_membership_household_and_volunteer_state(): void
    {
        $user = User::factory()->memberUser()->createQuietly();
        $this->actingAs($user);

        $household = Household::factory()->createQuietly();
        $user->member->updateQuietly([
            'household_id' => $household->id,
            'is_volunteer' => true,
        ]);

        Livewire::test(MembershipCardWidget::class)
            ->assertSee($user->member->membership->name)
            ->assertSee($user->member->name)
            ->assertSee(__('labels.yes'));
    }

    public function test_staff_do_not_see_member_widgets(): void
    {
        $user = User::factory()->createQuietly();
        $this->actingAs($user);

        static::assertFalse(MyOpenInvoicesWidget::canView());
        static::assertFalse(MyUpcomingActivitiesWidget::canView());
        static::assertFalse(MyStorageRentalsWidget::canView());
        static::assertFalse(MembershipCardWidget::canView());
    }
}
```

`inHousehold()` is an existing `MemberFactory` state. Run with:

```bash
./Taskfile artisan test --compact tests/Feature/Filament/Dashboard/MemberWidgetsTest.php
```
