# Storage Space Rentals Billing Integration Plan

## Overview

Extend the existing storage spaces feature with billing integration. Currently `StorageSpaceRental` is purely administrative (no billing). This plan adds:

1. A new `StorageSpaceLocation` entity (separate from `StorageSpace`) that carries a `BillableItem` with price and description per location.
2. A `BillableItemInstance` lifecycle on `StorageSpaceRental` — created when the rental is made, kept in sync with the rental's `end_date`, and stopped when the rental is deleted.
3. Refactor `StorageSpace` to reference `StorageSpaceLocation` via FK instead of a raw string `location` column.

The pattern follows how `MemberObjectType` / `Activity` own a `BillableItem` and how `ActivityMember` / `MemberObject` manage their `BillableItemInstance`.

---

## Database

### Migration 1: `create_storage_space_locations_table`

Table: `storage_space_locations`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `name` | string | unique, e.g. "Container 3", "Schuur Noord" |
| `billable_item_id` | foreignId | `constrained('billable_items')->cascadeOnDelete()` |
| `timestamps` | | |

Unique: `unique('name')`

### Migration 2: `add_location_fk_and_billable_instance_to_storage_spaces_and_rentals`

This migration performs two schema changes:

**Table: `storage_spaces`**
- Add `storage_space_location_id` (nullable FK to `storage_space_locations`, `cascadeOnDelete`)
- Populate the FK from existing `location` string values (create matching location rows)
- Make `storage_space_location_id` non-nullable after population
- Drop the `location` string column
- Change the unique constraint from `unique(['location', 'number'])` to `unique(['storage_space_location_id', 'number'])`

**Table: `storage_space_rentals`**
- Add `billable_item_instance_id` (nullable FK to `billable_item_instances`, `nullOnDelete`)

### Data Migration Strategy

Since this is a development database, the preferred approach is to run `task artisan migrate:fresh --seed` after creating the new `StorageSpaceLocationSeeder`. The seeder creates the three existing locations (`Container 3`, `Container 4`, `Container 5`) each with a default `BillableItem`.

The new seeder is called from the `DatabaseSeeder` class.

---

## Models

### New: `App\Models\StorageSpaceLocation`

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'billable_item_id'])]
final class StorageSpaceLocation extends Model
{
    use HasFactory;

    /** @return BelongsTo<BillableItem, $this> */
    public function billableItem(): BelongsTo
    {
        return $this->belongsTo(BillableItem::class);
    }

    /** @return HasMany<StorageSpace, $this> */
    public function storageSpaces(): HasMany
    {
        return $this->hasMany(StorageSpace::class);
    }
}
```

### Updated: `App\Models\StorageSpace`

Replace the string `location` column relationship with a FK to `StorageSpaceLocation`:

```php
#[Guarded('id', 'updated_at', 'created_at')]
final class StorageSpace extends Model
{
    use HasFactory;

    /** @return BelongsTo<StorageSpaceLocation, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(StorageSpaceLocation::class, 'storage_space_location_id');
    }

    /** @return HasMany<StorageSpaceRental, $this> */
    public function rentals(): HasMany
    {
        return $this->hasMany(StorageSpaceRental::class);
    }
}
```

Note: The relationship is named `location()` (not `storageSpaceLocation()`) because it is accessed as `$storageSpace->location` and `$storageSpaceRental->storageSpace->location`, which reads naturally.

### Updated: `App\Models\StorageSpaceRental`

Add the `billableItemInstance` relation and the `billable_item_instance_id` to `#[Fillable]`:

```php
#[Fillable(['storage_space_id', 'member_id', 'start_date', 'end_date', 'billable_item_instance_id'])]
#[ObservedBy([StorageSpaceRentalObserver::class])]
final class StorageSpaceRental extends Model
{
    use HasFactory;

    /** @return BelongsTo<StorageSpace, $this> */
    public function storageSpace(): BelongsTo
    {
        return $this->belongsTo(StorageSpace::class);
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** @return BelongsTo<BillableItemInstance, $this> */
    public function billableItemInstance(): BelongsTo
    {
        return $this->belongsTo(BillableItemInstance::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }
}
```

### Updated: `App\Models\Member`

No changes needed. The `storageSpaceRentals()` relation already exists.

---

## Domain Layer

### New: `App\Domain\StorageSpaceRentals\StorageSpaceRentalId`

```php
<?php

declare(strict_types=1);

namespace App\Domain\StorageSpaceRentals;

use App\Domain\NumericId;

final readonly class StorageSpaceRentalId extends NumericId
{
}
```

### New: `App\Domain\StorageSpaceRentals\StorageSpaceRental`

```php
<?php

declare(strict_types=1);

namespace App\Domain\StorageSpaceRentals;

use App\Domain\Invoices\Billing\BillableItemId;
use App\Domain\Members\MemberId;
use DateTimeImmutable;

final readonly class StorageSpaceRental
{
    public function __construct(
        public StorageSpaceRentalId $id,
        public MemberId $memberId,
        public BillableItemId $billableItemId,
        public DateTimeImmutable $startDate,
        public ?DateTimeImmutable $endDate,
    ) {
    }
}
```

### New: `App\Domain\StorageSpaceRentals\StorageSpaceRentalRepository`

```php
<?php

declare(strict_types=1);

namespace App\Domain\StorageSpaceRentals;

use App\Domain\Invoices\Billing\BillableItemInstanceId;
use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface StorageSpaceRentalRepository
{
    public function getById(StorageSpaceRentalId $rentalId): StorageSpaceRental;

    public function attachBillableItemInstance(StorageSpaceRentalId $rentalId, BillableItemInstanceId $instanceId): void;
}
```

### New: `App\Infrastructure\StorageSpaceRentals\StorageSpaceRentalDbRepository`

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\StorageSpaceRentals;

use App\Domain\Invoices\Billing\BillableItemId;
use App\Domain\Invoices\Billing\BillableItemInstanceId;
use App\Domain\Members\MemberId;
use App\Domain\StorageSpaceRentals\StorageSpaceRental as StorageSpaceRentalEntity;
use App\Domain\StorageSpaceRentals\StorageSpaceRentalId;
use App\Domain\StorageSpaceRentals\StorageSpaceRentalRepository;
use App\Models\StorageSpaceRental;

final class StorageSpaceRentalDbRepository implements StorageSpaceRentalRepository
{
    public function getById(StorageSpaceRentalId $rentalId): StorageSpaceRentalEntity
    {
        $model = StorageSpaceRental::with('storageSpace.location.billableItem')->findOrFail($rentalId->value);

        return new StorageSpaceRentalEntity(
            id: StorageSpaceRentalId::create($model->id),
            memberId: MemberId::create($model->member_id),
            billableItemId: BillableItemId::create($model->storageSpace->location->billable_item_id),
            startDate: $model->start_date->toDateTimeImmutable(),
            endDate: $model->end_date?->toDateTimeImmutable(),
        );
    }

    public function attachBillableItemInstance(StorageSpaceRentalId $rentalId, BillableItemInstanceId $instanceId): void
    {
        StorageSpaceRental::query()
            ->where('id', $rentalId->value)
            ->update(['billable_item_instance_id' => $instanceId->value]);
    }
}
```

---

## Billing Item Applicator

### Updated: `App\Domain\Invoices\Billing\BillableItemInstanceRepository`

Add optional `$startDate` parameter to `add()` and new `updateEndDate()` method:

```php
public function add(MemberId $memberId, BillableItemId $billableItemId, ?DateTimeInterface $endDate = null, ?DateTimeInterface $startDate = null): BillableItemInstanceId;

public function updateEndDate(BillableItemInstanceId $instanceId, ?DateTimeInterface $endDate): void;
```

### Updated: `App\Infrastructure\Invoices\Billing\BillableItemDbInstanceRepository`

Update `add()` to use the optional `$startDate`, falling back to `CarbonImmutable::now()`, and implement `updateEndDate()`:

```php
public function add(MemberId $memberId, BillableItemId $billableItemId, ?DateTimeInterface $endDate = null, ?DateTimeInterface $startDate = null): BillableItemInstanceId
{
    $billableItem = BillableItem::findOrFail($billableItemId->value);
    $instance = BillableItemInstance::create([
        'member_id' => $memberId->value,
        'billable_item_id' => $billableItemId->value,
        'start_date' => $startDate ?? CarbonImmutable::now(),
        'end_date' => $endDate,
        'bill_cycle_in_months' => $billableItem->bill_period->toBillPeriodInMonths(),
    ]);

    return BillableItemInstanceId::create($instance->id);
}

public function updateEndDate(BillableItemInstanceId $instanceId, ?DateTimeInterface $endDate): void
{
    BillableItemInstance::query()
        ->where('id', $instanceId->value)
        ->update(['end_date' => $endDate]);
}
```

### New: `App\Domain\Invoices\Billing\BillingItemApplicators\ApplyStorageSpaceRentalBilling`

```php
<?php

declare(strict_types=1);

namespace App\Domain\Invoices\Billing\BillingItemApplicators;

use App\Domain\Invoices\Billing\BillableItemInstanceId;
use App\Domain\StorageSpaceRentals\StorageSpaceRentalId;
use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface ApplyStorageSpaceRentalBilling
{
    public function apply(StorageSpaceRentalId $rentalId): void;

    public function updateEndDate(BillableItemInstanceId $billableItemInstanceId, ?DateTimeInterface $endDate): void;

    public function stop(BillableItemInstanceId $billableItemInstanceId): void;
}
```

### New: `App\Domain\Invoices\Billing\BillingItemApplicators\ApplyStorageSpaceRentalBillingImpl`

```php
<?php

declare(strict_types=1);

namespace App\Domain\Invoices\Billing\BillingItemApplicators;

use App\Domain\Invoices\Billing\BillableItemInstanceId;
use App\Domain\Invoices\Billing\BillableItemInstanceRepository;
use App\Domain\StorageSpaceRentals\StorageSpaceRentalId;
use App\Domain\StorageSpaceRentals\StorageSpaceRentalRepository;
use DateTimeInterface;

final readonly class ApplyStorageSpaceRentalBillingImpl implements ApplyStorageSpaceRentalBilling
{
    public function __construct(
        private StorageSpaceRentalRepository $storageSpaceRentalRepository,
        private BillableItemInstanceRepository $billableItemInstanceRepository,
    ) {
    }

    public function apply(StorageSpaceRentalId $rentalId): void
    {
        $rental = $this->storageSpaceRentalRepository->getById($rentalId);

        $instanceId = $this->billableItemInstanceRepository->add(
            $rental->memberId,
            $rental->billableItemId,
            $rental->endDate,
            $rental->startDate,
        );

        $this->storageSpaceRentalRepository->attachBillableItemInstance($rentalId, $instanceId);
    }

    public function updateEndDate(BillableItemInstanceId $billableItemInstanceId, ?DateTimeInterface $endDate): void
    {
        $this->billableItemInstanceRepository->updateEndDate($billableItemInstanceId, $endDate);
    }

    public function stop(BillableItemInstanceId $instanceId): void
    {
        $this->billableItemInstanceRepository->stop($instanceId);
    }
}
```

---

## Observer

### New: `App\Observers\StorageSpaceRentalObserver`

Follows the exact pattern of `ActivityMemberObserver` — delegates all billing logic to the applicator.

```php
<?php

declare(strict_types=1);

namespace App\Observers;

use App\Domain\Invoices\Billing\BillableItemInstanceId;
use App\Domain\Invoices\Billing\BillingItemApplicators\ApplyStorageSpaceRentalBilling;
use App\Domain\StorageSpaceRentals\StorageSpaceRentalId;
use App\Models\StorageSpaceRental;

final readonly class StorageSpaceRentalObserver
{
    public function __construct(private ApplyStorageSpaceRentalBilling $applyStorageSpaceRentalBilling)
    {
    }

    public function created(StorageSpaceRental $rental): void
    {
        $this->applyStorageSpaceRentalBilling->apply(
            StorageSpaceRentalId::create($rental->id),
        );
    }

    public function updated(StorageSpaceRental $rental): void
    {
        if ($rental->wasChanged('end_date') && $rental->billable_item_instance_id !== null) {
            $this->applyStorageSpaceRentalBilling->updateEndDate(
                BillableItemInstanceId::create($rental->billable_item_instance_id),
                $rental->end_date,
            );
        }
    }

    public function deleted(StorageSpaceRental $rental): void
    {
        if (!$rental->billable_item_instance_id) {
            return;
        }

        $this->applyStorageSpaceRentalBilling->stop(
            BillableItemInstanceId::create($rental->billable_item_instance_id)
        );
    }
}
```

Key decisions:
- `created` delegates entirely to the applicator, which creates the `BillableItemInstance` and attaches the instance ID back to the rental via a query builder update (no observer loop).
- `updated` delegates to the applicator's `updateEndDate()` — keeping all billing logic out of the observer.
- `deleted` delegates to the applicator's `stop()`, matching the `ActivityMemberObserver` pattern exactly.

---

## Factories

### New: `database/factories/StorageSpaceLocationFactory.php`

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Invoices\Billing\BillPeriod;
use App\Models\BillableItem;
use App\Models\StorageSpaceLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StorageSpaceLocation>
 */
final class StorageSpaceLocationFactory extends Factory
{
    protected $model = StorageSpaceLocation::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->randomElement([
                'Container 3',
                'Container 4',
                'Container 5',
            ]),
            'billable_item_id' => BillableItem::factory()->state([
                'description' => static fn (array $attributes) => 'Opslagplek: ' . $attributes['name'],
                'bill_period' => BillPeriod::Annually,
            ]),
        ];
    }
}
```

### Updated: `database/factories/StorageSpaceFactory.php`

Replace `location` (string) with `storage_space_location_id`:

```php
final class StorageSpaceFactory extends Factory
{
    protected $model = StorageSpace::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'storage_space_location_id' => StorageSpaceLocation::factory(),
            'number' => $this->faker->numberBetween(1, 30),
        ];
    }
}
```

### Updated: `database/factories/StorageSpaceRentalFactory.php`

No structural changes needed — the rental already references `storage_space_id`. The observable will handle `BillableItemInstance` creation. The `billable_item_instance_id` can be left null in the definition:

```php
final class StorageSpaceRentalFactory extends Factory
{
    protected $model = StorageSpaceRental::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'storage_space_id' => StorageSpace::factory(),
            'member_id' => Member::factory(),
            'start_date' => $this->faker->date(),
            'end_date' => null,
        ];
    }
}
```

---

## Seeder

### New: `database/seeders/StorageSpaceLocationSeeder.php`

```php
<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Invoices\Billing\BillPeriod;
use App\Models\BillableItem;
use App\Models\StorageSpaceLocation;
use Illuminate\Database\Seeder;

final class StorageSpaceLocationSeeder extends Seeder
{
    public function run(): void
    {
        $locations = ['Container 3', 'Container 4', 'Container 5'];

        foreach ($locations as $name) {
            $billableItem = BillableItem::create([
                'description' => "Opslagplek {$name}",
                'price' => 0,
                'vat' => 0,
                'bill_period' => BillPeriod::Annually,
            ]);

            StorageSpaceLocation::create([
                'name' => $name,
                'billable_item_id' => $billableItem->id,
            ]);
        }
    }
}
```

### Updated: `database/seeders/DevelopmentSeeder.php`

Replace the `StorageSpace::factory()` calls that set `'location' => 'Container X'` with `StorageSpaceLocation` references. Import the new seeder and `StorageSpaceLocation` model.

---

## Filament Resources

### New: `StorageSpaceLocationResource`

Path: `app/Filament/Admin/Resources/StorageSpaceLocations/`

Structure mirrors `MemberObjectTypeResource`:

```
StorageSpaceLocations/
  StorageSpaceLocationResource.php
  Pages/
    ListStorageSpaceLocations.php
    CreateStorageSpaceLocation.php
    EditStorageSpaceLocation.php
  Schemas/
    StorageSpaceLocationForm.php
  Tables/
    StorageSpaceLocationsTable.php
```

**`StorageSpaceLocationResource.php`**:
- Model: `StorageSpaceLocation::class`
- NavigationGroup: `NavigationGroup::MemberAdministration`
- NavigationIcon: `Heroicon::MapPin`

**`StorageSpaceLocationForm.php`** (mirrors `MemberObjectTypeForm.php`):

```php
Section::make()->schema([
    TextInput::make('name')->label(__('labels.name'))->required()->unique(),
]),
Section::make(__('labels.billing'))
    ->relationship('billableItem')
    ->schema([
        TextInput::make('description')->label(__('labels.description'))->required(),
        TextInput::make('price')->label(__('labels.price'))->required(),
        Select::make('bill_period')
            ->label(__('labels.bill_period'))
            ->options(BillPeriodLabels::options()),
    ]),
```

**`CreateStorageSpaceLocation.php`** (mirrors `CreateMemberObjectType.php`):

```php
protected function handleRecordCreation(array $data): Model
{
    $item = BillableItem::createDefault([
        'description' => 'Opslagplek: ' . $data['name'],
    ]);

    return parent::handleRecordCreation([
        ...$data,
        'billable_item_id' => $item->id,
    ]);
}
```

**`StorageSpaceLocationsTable.php`**:
Columns: `name`, `billableItem.description`, `billableItem.price` (via `PriceFormatter`)

### Updated: `StorageSpaceResource`

Change the form, table, and the `GenerateStorageSpacesAction` to use `StorageSpaceLocation` instead of the string `location`.

**`StorageSpaceForm.php`**:
Replace `TextInput::make('location')` with:

```php
Select::make('storage_space_location_id')
    ->label(__('labels.location'))
    ->relationship('location', 'name')
    ->required(),
TextInput::make('number')
    ->label(__('labels.space_number'))
    ->required(),
```

**`StorageSpacesTable.php`**:
Replace `TextColumn::make('location')` with:

```php
TextColumn::make('location.name')
    ->label(__('labels.location'))
    ->sortable()
    ->searchable(),
```

**`GenerateStorageSpacesAction.php`**:
Replace `TextInput::make('location')` with:

```php
Select::make('storage_space_location_id')
    ->label(__('labels.location'))
    ->relationship('location', 'name')
    ->required(),
```

Update the action closure to use `storage_space_location_id` instead of `location`:

```php
->action(static function (array $data): void {
    $locationId = (int) $data['storage_space_location_id'];
    $fromNumber = (int) $data['from_number'];
    $toNumber = (int) $data['to_number'];

    for ($number = $fromNumber; $number <= $toNumber; $number++) {
        StorageSpace::firstOrCreate([
            'storage_space_location_id' => $locationId,
            'number' => $number,
        ]);
    }
})
```

### Updated: `StorageSpaceRentalForm`

**`forStorageSpace` variant**:
The `storage_space_id` is already set via `Hidden`. The location is implicit from the space.

**`forMember` variant**:
Replace the `Select::make('location')` string-based dropdown with one that queries `StorageSpaceLocation`:

```php
Select::make('storage_space_location_id')
    ->label(__('labels.location'))
    ->options(
        static fn (): array =>
            StorageSpaceLocation::query()
                ->orderBy('name')
                ->pluck('name', 'id')
                ->toArray()
    )
    ->required()
    ->live()
    ->dehydrated(false)
    ->formatStateUsing(static fn (?StorageSpaceRental $record): ?string => $record?->storageSpace?->location?->name)
    ->afterStateUpdated(static fn (Set $set) => $set('storage_space_id', null)),
Select::make('storage_space_id')
    ->label(__('labels.space_number'))
    ->options(
        static fn (Get $get): array =>
            StorageSpace::query()
                ->where('storage_space_location_id', $get('storage_space_location_id'))
                ->pluck('number', 'id')
                ->toArray()
    )
    ->searchable()
    ->getSearchResultsUsing(
        static fn (string $search, Get $get): array =>
            StorageSpace::query()
                ->where('storage_space_location_id', $get('storage_space_location_id'))
                ->where('number', 'like', "%{$search}%")
                ->pluck('number', 'id')
                ->toArray()
    )
    ->required()
    ->live(),
```

### Updated: Member's `StorageSpaceRentalsRelationManager`

**`app/Filament/Admin/Resources/Members/RelationManagers/StorageSpaceRentalsRelationManager.php`**:
Update the `storageSpace.location` column reference to `storageSpace.location.name`:

```php
TextColumn::make('storageSpace.location.name')->label(__('labels.location')),
```

### Updated: StorageSpace's `StorageSpaceRentalsRelationManager`

**`app/Filament/Admin/Resources/StorageSpaces/RelationManagers/StorageSpaceRentalsRelationManager.php`**:
No structural changes needed — it already shows `member.name`, `start_date`, and `end_date`. The location is implicit from the parent record.

---

## Validation Rule

### Updated: `app/Rules/NoOverlappingStorageSpaceRental.php`

No structural changes needed. The rule already validates by `storage_space_id`. The `storage_space_id` is untouched by this refactor.

---

## Language Labels

### Add to `lang/nl/labels.php`:

```php
'storage_space_location' => 'Opslaglocatie',
'storage_space_locations' => 'Opslaglocaties',
```

Update the existing keys to use `Opslagplek` consistently (they already do — no change needed).

### Add to `lang/nl/notifications.php`:

No changes needed. Existing notifications are sufficient.

---

## Tests

### New: `tests/Feature/StorageSpaces/StorageSpaceRentalObserverTest.php`

Observer integration tests (mirrors `ActivityMemberObserverTest` style):

1. **`test_it_creates_billable_item_instance_when_rental_is_created`**: Create a `StorageSpaceRental` via factory (which uses `StorageSpaceLocation` with a `BillableItem`), assert a `BillableItemInstance` was created with matching `member_id`, `billable_item_id`, `start_date`, `end_date`.

2. **`test_it_does_not_create_instance_when_rental_is_created_with_no_location_billable_item`**: Edge case — if the location has no billable item (unlikely after this refactor, but covered). Note: the current schema makes `billable_item_id` non-nullable, so this case cannot occur after migration.

3. **`test_it_updates_instance_end_date_when_rental_end_date_changes`**: Create a rental, update its `end_date`, assert the instance's `end_date` was updated.

4. **`test_it_does_not_update_instance_when_other_attributes_change`**: Create a rental, change unrelated attributes, assert instance `end_date` unchanged.

5. **`test_it_stops_instance_when_rental_is_deleted`**: Create a rental, delete it, assert the instance's `end_date` is set to now.

### New: `tests/Feature/Filament/StorageSpaceLocations/StorageSpaceLocationResourceTest.php`

Standard Filament resource tests:

- Can list storage space locations
- Can create a storage space location (with billable item)
- Can edit a storage space location
- Can delete a storage space location

### Updated: `tests/Feature/StorageSpaces/GenerateStorageSpacesActionTest.php`

Update to use `storage_space_location_id` instead of `location` string. Create a `StorageSpaceLocation` before each test and pass its ID.

### Updated: `tests/Feature/Filament/StorageSpaces/StorageSpaceResourceTest.php`

Update assertions to use `storage_space_location_id` instead of `location` string. Create a `StorageSpaceLocation` for each test.

### Updated: `tests/Feature/StorageSpaces/NoOverlappingStorageSpaceRentalTest.php`

No changes needed — the rule works on `storage_space_id` which is unchanged.

---

## Implementation Order

1. Create `StorageSpaceLocation` model + migration + factory
2. Create migration to add `storage_space_location_id` to `storage_spaces`, drop `location`, add `billable_item_instance_id` to `storage_space_rentals`
3. Update `StorageSpace` model (`location()` relation instead of string `location` attribute)
4. Update `StorageSpaceRental` model (add `billableItemInstance()` relation, add `#[ObservedBy]`)
5. Create domain layer (`StorageSpaceRentalId`, entity, `StorageSpaceRentalRepository` interface + `StorageSpaceRentalDbRepository`)
6. Update `BillableItemInstanceRepository` to accept optional `$startDate` in `add()` and add `updateEndDate()`
7. Create `ApplyStorageSpaceRentalBilling` applicator (interface + implementation)
8. Create `StorageSpaceRentalObserver`
9. Create `StorageSpaceLocationSeeder` and register in `DatabaseSeeder`
10. Update `DevelopmentSeeder` to use `StorageSpaceLocation`
11. Update `StorageSpaceFactory` to use `StorageSpaceLocation`
12. Create `StorageSpaceLocationResource` (Filament)
13. Update `StorageSpaceResource` — form, table, action
14. Update `StorageSpaceRentalForm` — replace string location select with FK-based select
15. Update both `StorageSpaceRentalsRelationManager` classes (update column references)
16. Update `GenerateStorageSpacesAction`
17. Add language labels
18. Run `task artisan migrate:fresh --seed`
19. Write tests

---

## Key Design Decisions

| Decision | Rationale |
|---|---|
| `StorageSpaceLocation` owns the `BillableItem` | Each location can have its own price/description for storage rentals. Mirrors `MemberObjectType` / `Activity` pattern. |
| Applicator pattern for billing | Follows the same pattern as `ActivityMemberObserver` / `ApplyActivityBilling`. The observer delegates all billing lifecycle (apply, updateEndDate, stop) to `ApplyStorageSpaceRentalBilling`, keeping domain logic out of the observer. |
| `billable_item_instance_id` stored on rental | Enables direct instance lookup for updates/deletes. Mirrors `ActivityMember.billable_item_instance_id`. |
| Query builder update in `attachBillableItemInstance()` | Avoids observer loops when updating the rental's own FK, equivalent to `updateQuietly()` but via the repository pattern. |
| `updated` delegates to applicator `updateEndDate()` | Keeps all billing mutations behind the applicator interface, consistent with `apply()` and `stop()`. |
| `deleted` delegates to applicator `stop()` | Consistent with `BillableItemInstance::stop()` and the rest of the billing system. |
| Location relationship named `location()` | Reads naturally: `$storageSpace->location`, `$storageSpaceRental->storageSpace->location->name`. |
| `GenerateStorageSpacesAction` takes `storage_space_location_id` | The action needs to know which location to create spaces for. A select is more user-friendly than a text input. |

---

## Files Created

- `database/migrations/..._create_storage_space_locations_table.php`
- `database/migrations/..._add_location_fk_and_billable_instance_to_storage_spaces_and_rentals.php`
- `app/Models/StorageSpaceLocation.php`
- `app/Domain/StorageSpaceRentals/StorageSpaceRentalId.php`
- `app/Domain/StorageSpaceRentals/StorageSpaceRental.php`
- `app/Domain/StorageSpaceRentals/StorageSpaceRentalRepository.php`
- `app/Infrastructure/StorageSpaceRentals/StorageSpaceRentalDbRepository.php`
- `app/Domain/Invoices/Billing/BillingItemApplicators/ApplyStorageSpaceRentalBilling.php`
- `app/Domain/Invoices/Billing/BillingItemApplicators/ApplyStorageSpaceRentalBillingImpl.php`
- `app/Observers/StorageSpaceRentalObserver.php`
- `database/factories/StorageSpaceLocationFactory.php`
- `database/seeders/StorageSpaceLocationSeeder.php`
- `app/Filament/Admin/Resources/StorageSpaceLocations/StorageSpaceLocationResource.php`
- `app/Filament/Admin/Resources/StorageSpaceLocations/Pages/ListStorageSpaceLocations.php`
- `app/Filament/Admin/Resources/StorageSpaceLocations/Pages/CreateStorageSpaceLocation.php`
- `app/Filament/Admin/Resources/StorageSpaceLocations/Pages/EditStorageSpaceLocation.php`
- `app/Filament/Admin/Resources/StorageSpaceLocations/Schemas/StorageSpaceLocationForm.php`
- `app/Filament/Admin/Resources/StorageSpaceLocations/Tables/StorageSpaceLocationsTable.php`
- `tests/Feature/StorageSpaces/StorageSpaceRentalObserverTest.php`
- `tests/Feature/Filament/StorageSpaceLocations/StorageSpaceLocationResourceTest.php`

## Files Modified

- `app/Domain/Invoices/Billing/BillableItemInstanceRepository.php` (add optional `$startDate` to `add()`, add `updateEndDate()`)
- `app/Infrastructure/Invoices/Billing/BillableItemDbInstanceRepository.php` (implement `$startDate` fallback in `add()`, implement `updateEndDate()`)
- `app/Models/StorageSpace.php` (replace `location` string with `location()` BelongsTo relation)
- `app/Models/StorageSpaceRental.php` (add `billableItemInstance()` relation, add `#[ObservedBy]`)
- `database/factories/StorageSpaceFactory.php` (use `storage_space_location_id` instead of `location`)
- `database/factories/StorageSpaceRentalFactory.php` (no structural changes needed)
- `database/seeders/DevelopmentSeeder.php` (use `StorageSpaceLocation`)
- `database/seeders/DatabaseSeeder.php` (register `StorageSpaceLocationSeeder`)
- `app/Filament/Admin/Resources/StorageSpaces/StorageSpaceResource.php` (no changes needed)
- `app/Filament/Admin/Resources/StorageSpaces/Schemas/StorageSpaceForm.php` (location select)
- `app/Filament/Admin/Resources/StorageSpaces/Schemas/StorageSpaceRentalForm.php` (location select)
- `app/Filament/Admin/Resources/StorageSpaces/Tables/StorageSpacesTable.php` (location column)
- `app/Filament/Admin/Resources/StorageSpaces/Actions/GenerateStorageSpacesAction.php` (location select + action closure)
- `app/Filament/Admin/Resources/Members/RelationManagers/StorageSpaceRentalsRelationManager.php` (location column)
- `lang/nl/labels.php` (add location label)
- `tests/Feature/StorageSpaces/GenerateStorageSpacesActionTest.php` (use location ID)
- `tests/Feature/Filament/StorageSpaces/StorageSpaceResourceTest.php` (use location ID)
