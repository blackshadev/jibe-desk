# StorageSpaces Table Enhancements — Implementation Plan

## Overview

Enhance the StorageSpaces table to show rental information (member name + rental end date), add tabs for filtering available/unavailable spaces, and add a select filter for location.

## Requirements

1. **Rental columns**: Show which member a storage space is rented to, and until when (the end date of the current active rental).
2. **Tabs**: Filter by available (no active rental) and unavailable (has active rental) using tabs, following the `ListActivities` pattern.
3. **Location filter**: Add a select filter to filter by `StorageSpaceLocation`.

---

## Data Model Context

- **`StorageSpace`** has a `hasMany` relationship to `StorageSpaceRental` via `rentals()`.
- **`StorageSpaceRental`** belongs to `Member` via `member()` and has `start_date` (date) and `end_date` (nullable date).
- A storage space is **available** when it has NO active rental (a rental where `end_date` is null OR `end_date` is in the future).
- A storage space is **unavailable** when it HAS an active rental.
- **`StorageSpaceLocation`** is the "location" model, related via `StorageSpace.location()` (BelongsTo, FK: `storage_space_location_id`).

---

## Implementation Steps

### Step 1: Add `currentRental` and `isAvailable` relationships/scopes to `StorageSpace` model

**File**: `app/Models/StorageSpace.php`

Add a `currentRental` hasOne relationship that returns the active rental for a storage space. An active rental is one where the `start_date` is in the past and `end_date` is null OR `end_date` is in the future.

```php
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** @return HasOne<StorageSpaceRental, $this> */
public function currentRental(): HasOne
{
    return $this
        ->hasOne(StorageSpaceRental::class)
        ->whereNowOrPast('start_date')
        ->where(static fn (Builder $query) => $query->whereNull('end_date')->orWhereFuture('end_date'))
        ->oldest('start_date');
}

#[Scope]
protected function available(Builder $query): Builder
{
    return $query->whereDoesntHave('currentRental');
}

#[Scope]
protected function unavailable(Builder $query): Builder
{
    return $query->whereHas('currentRental');
}
```

> **Note**: The `currentRental` relationship uses `orWhereFuture('end_date')` which leverages Laravel 13's date comparison helpers, consistent with the `Activity::active()` scope pattern already in the codebase.

### Step 2: Add rental columns to `StorageSpacesTable`

**File**: `app/Filament/Admin/Resources/StorageSpaces/Tables/StorageSpacesTable.php`

Add two new columns to show the member name and rental end date from the `currentRental` relationship:

```php
TextColumn::make('currentRental.member.name')
    ->label(__('labels.member'))
    ->placeholder('-'),
TextColumn::make('currentRental.end_date')
    ->label(__('labels.rented_until'))
    ->date()
    ->placeholder('-'),
```

The `placeholder('-')` on the end_date column will show a dash when there is no current rental (i.e., the space is available).

Also add the location select filter:

```php
use Filament\Tables\Filters\SelectFilter;

SelectFilter::make('storage_space_location_id')
    ->label(__('labels.location'))
    ->relationship('location', 'name'),
```

The full updated `configure` method:

```php
public static function configure(Table $table): Table
{
    return $table
        ->columns([
            TextColumn::make('location.name')
                ->label(__('labels.location'))
                ->sortable()
                ->searchable(),
            TextColumn::make('number')
                ->label(__('labels.space_number'))
                ->sortable()
                ->numeric(),
            TextColumn::make('currentRental.member.name')
                ->label(__('labels.member'))
                ->searchable(),
            TextColumn::make('currentRental.end_date')
                ->label(__('labels.rented_until'))
                ->date()
                ->placeholder('—'),
        ])
        ->filters([
            SelectFilter::make('storage_space_location_id')
                ->label(__('labels.location'))
                ->relationship('location', 'name'),
        ])
        ->defaultSort('location.name')
        ->recordActions([
            EditAction::make(),
            DeleteAction::make(),
        ])
        ->toolbarActions([
            BulkActionGroup::make([
                DeleteBulkAction::make(),
            ]),
        ]);
}
```

### Step 3: Add tabs to `ListStorageSpaces` page

**File**: `app/Filament/Admin/Resources/StorageSpaces/Pages/ListStorageSpaces.php`

Add tabs following the exact same pattern as `ListActivities`. Use the new `available()` and `unavailable()` scopes:

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\StorageSpaces\Pages;

use App\Filament\Admin\Resources\StorageSpaces\Actions\GenerateStorageSpacesAction;
use App\Filament\Admin\Resources\StorageSpaces\StorageSpaceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs;
use Illuminate\Database\Eloquent\Builder;

final class ListStorageSpaces extends ListRecords
{
    protected static string $resource = StorageSpaceResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tabs\Tab::make(__('labels.all')),
            'available' => Tabs\Tab::make(__('labels.available'))
                ->modifyQueryUsing(
                    /** @phpstan-ignore-next-line method.notFound */
                    static fn (Builder $query) => $query->available()
                ),
            'unavailable' => Tabs\Tab::make(__('labels.unavailable'))
                ->modifyQueryUsing(
                    /** @phpstan-ignore-next-line method.notFound */
                    static fn (Builder $query) => $query->unavailable()
                ),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            GenerateStorageSpacesAction::make(),
        ];
    }
}
```

### Step 4: Add missing translation labels

**File**: `lang/nl/labels.php`

Add the following new labels:

```php
'member' => 'Lid',            // Already exists as 'member' => 'Lid' on line 10
'rented_until' => 'Verhuurd tot',
'available' => 'Beschikbaar',
'unavailable' => 'Niet beschikbaar',
```

Note: `'member' => 'Lid'` already exists on line 10 of the labels file, so only `rented_until`, `available`, and `unavailable` need to be added.

---

## Files to Modify

| File | Change |
|------|--------|
| `app/Models/StorageSpace.php` | Add `currentRental()` HasOne relationship, `available()` scope, `unavailable()` scope |
| `app/Filament/Admin/Resources/StorageSpaces/Tables/StorageSpacesTable.php` | Add `currentRental.member.name` and `currentRental.end_date` columns, add location `SelectFilter` |
| `app/Filament/Admin/Resources/StorageSpaces/Pages/ListStorageSpaces.php` | Add `getTabs()` method with all/available/unavailable tabs |
| `lang/nl/labels.php` | Add `rented_until`, `available`, `unavailable` labels |

---

## Potential Issues & Considerations

1. **`currentRental` relationship performance**: The `hasOne` with `whereNull('end_date')->orWhereFuture('end_date')` needs to be wrapped in a closure to avoid polluting the outer query. The implementation above uses `where(fn => ...)` to properly scope the OR condition.

2. **Eager loading**: When the table displays `currentRental.member.name`, Filament will automatically eager-load the relationship. However, for the tabs (available/unavailable), the `whereHas`/`whereDoesntHave` queries will use subqueries which should be efficient given the expected data size.

3. **`@phpstan-ignore-next-line method.notFound`**: The `available()` and `unavailable()` scopes are defined using Laravel's `#[Scope]` attribute, which PHPStan doesn't recognize as methods on the Builder. The `@phpstan-ignore-next-line` annotation is needed, consistent with the existing pattern in `ListActivities`.

4. **`currentRental` may return null**: When a storage space has no active rental, `currentRental` will be null. The `placeholder('—')` on the end_date column handles this gracefully. For the member name column, Filament's TextColumn handles null relationships by showing nothing.

5. **Bug in `ListActivities.php`**: Line 26 has `'inactive' => Tabs\Tab::make(__('labels.active'))` — it should be `__('labels.inactive')`. This is a pre-existing bug, not part of this feature request, but worth noting.
