# Member Objects Feature Plan

## Overview

Introduce an `MemberObject` model (representing any physical item) that belongs to a `Member`, with a related `MemberObjectType` model. Object types are seeded (`tag`, `sleutel`, `anders`), optionally linked to a `BillableItem`. Both models are exposed as Filament resources, and objects are also manageable from the `MemberResource` via a `RelationManager`.

---

## Database

### Migration 1: `create_object_types_table`

Table: `member_object_types`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `name` | string | e.g. `tag`, `sleutel`, `anders` |
| `billable_item_id` | foreignId nullable | `constrained('billable_items')->nullOnDelete()` |
| `timestamps` | | |

### Migration 2: `create_member_objects_table`

Table: `member_objects`

| Column | Type | Notes                                                    |
|---|---|----------------------------------------------------------|
| `id` | bigint PK |                                                          |
| `member_id` | foreignId | `constrained('members')->cascadeOnDelete()`              |
| `object_type_id` | foreignId | `constrained('member_object_types')->restrictOnDelete()` |
| `name` | string |                                                          |
| `start_date` | date |                                                          |
| `end_date` | date nullable |                                                          |
| `timestamps` | |                                                          |

---

## Models

### `App\Models\ObjectType`

```php
#[Fillable(['name', 'billable_item_id'])]
final class MemberObjectType extends Model
{
    use HasFactory;

    /** @return BelongsTo<BillableItem, $this> */
    public function billableItem(): BelongsTo
    {
        return $this->belongsTo(BillableItem::class);
    }

    /** @return HasMany<MemberObject, $this> */
    public function objects(): HasMany
    {
        return $this->hasMany(MemberObject::class);
    }
}
```

### `App\Models\MemberObject`

```php
#[Fillable(['member_id', 'object_type_id', 'name', 'start_date', 'end_date'])]
final class MemberObject extends Model
{
    use HasFactory;

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** @return BelongsTo<MemberObjectType, $this> */
    public function memberObjectType(): BelongsTo
    {
        return $this->belongsTo(MemberObjectType::class);
    }

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }
}
```

### Update `App\Models\Member`

Add a `HasMany` relation:

```php
/** @return HasMany<MemberObject, $this> */
public function memberObjects(): HasMany
{
    return $this->hasMany(MemberObject::class);
}
```

---

## Seeder

Create `App\Seeders\MemberObjectTypeSeeder` (or add to `DatabaseSeeder` — **not** only `DevelopmentSeeder` since these are production data):

```php
// database/seeders/MemberObjectTypeSeeder.php
final class MemberObjectTypeSeeder extends Seeder
{
    public function run(): void
    {
        $tagBillableItem = BillableItem::create([
            'description' => 'Tag',
            'price' => 20.00,
            'vat' => 0,
            'bill_period' => BillPeriod::Once, // see note below
        ]);

        MemberObjectType::create(['name' => 'tag', 'billable_item_id' => $tagBillableItem->id]);

        $sleutelBillableItem = BillableItem::create([
            'description' => 'Sleutel',
            'price' => 20.00,
            'vat' => 0,
            'bill_period' => BillPeriod::Once,
        ]);

        MemberObjectType::create(['name' => 'sleutel', 'billable_item_id' => $sleutelBillableItem->id]);

        MemberObjectType::create(['name' => 'anders', 'billable_item_id' => null]);
    }
}
```

**Call from `DatabaseSeeder::run()`** (not just `DevelopmentSeeder`), so it runs in all environments:

```php
$this->call(MemberObjectTypeSeeder::class);
```

> **`BillPeriod::Once`**: Check if `BillPeriod` enum already has an `Once` case in `app/Domain/Invoices/Billing/BillPeriod.php`. If it does not, add it. "Billed only once" requires a `once` period value. This is a required change to the domain enum.

---

## Domain

### Add `BillPeriod::Once`

File: `app/Domain/Invoices/Billing/BillPeriod.php`

Add `case Once = 'once';` and a corresponding label in `lang/nl/labels.php`:
```php
BillPeriod::Once->value => 'Eenmalig',
```

---

## Factories

### `database/factories/MemberObjectTypeFactory.php`

```php
final class MemberObjectTypeFactory extends Factory
{
    protected $model = MemberObjectType::class;

    public function definition(): array
    {
        return [
            'name' => fake()->word(),
            'billable_item_id' => null,
        ];
    }
}
```

### `database/factories/MemberObjectFactory.php`

```php
final class MemberObjectFactory extends Factory
{
    protected $model = MemberObject::class;

    public function definition(): array
    {
        return [
            'member_id' => Member::factory(),
            'object_type_id' => MemberObjectType::factory(),
            'name' => function (array $attributes) {,
                $objectType = MemberObjectType::find($attributes['object_type_id']);
                
                switch ($objectType->name ?? null) {
                    case 'tag':
                        return 'Tag ' . fake()->numerify('0157#####');
                    case 'sleutel':
                        return 'Sleutel ' . fake()->randomElement(['Kantine', 'Berging', 'Container 3', 'Container 4']);
                    default:
                        return fake()->word();
                }
            },
            'start_date' => fake()->date(),
            'end_date' => null,
        ];
    }
}
```

---

## Filament Resources

### `ObjectTypeResource`

Path: `app/Filament/Admin/Resources/ObjectTypes/`

Structure (mirroring `ExtraMembershipItemResource`):

```
MemberObjectTypes/
  MemberObjectTypeResource.php
  Pages/
    ListMemberObjectTypes.php
    CreateMemberObjectType.php
    EditMemberObjectType.php
  Schemas/
    MemberObjectTypeForm.php
  Tables/
    MemberObjectTypesTable.php
```

**`MemberObjectTypeResource.php`**:
- Model: `MemberObjectType::class`
- NavigationGroup: `NavigationGroup::MemberAdministration`
- NavigationIcon: `Heroicon::Tag`
- Labels: `labels.member_object_type` / `labels.member_object_types`

**`MemberObjectTypeForm.php`**: Fields:
- `TextInput::make('name')->required()`
- `Section` with relationship `billableItem`: `description`, `price`, `bill_period` (same pattern as `ActivityForm`)

**`MemberObjectTypesTable.php`**: Columns:
- `name`
- `billableItem.description`
- `billableItem.price` (formatted via `PriceFormatter`)

---

### `MemberObjectResource`

Path: `app/Filament/Admin/Resources/MemberObjects/`

```
MemberObjects/
  MemberObjectResource.php
  Pages/
    ListMemberObjects.php
    CreateMemberObject.php
    EditMemberObject.php
  Schemas/
    MemberObjectForm.php
  Tables/
    MemberObjectsTable.php
```

**`MemberObjectResource.php`**:
- Model: `MemberObject::class`
- NavigationGroup: `NavigationGroup::MemberAdministration`
- NavigationIcon: `Heroicon::ArchiveBox`
- Labels: `labels.member_object` / `labels.member_objects`

**`MemberObjectForm.php`**: Fields:
- `Select::make('member_id')` — searchable, relationship to `Member`
- `Select::make('member_object_type_id')` — relationship to `MemberObjectType`, shows `name`
- `TextInput::make('name')->required()`
- `DatePicker::make('start_date')->required()`
- `DatePicker::make('end_date')`

**`MemberObjectsTable.php`**: Columns:
- `member.name`
- `objectType.name`
- `name`
- `start_date`
- `end_date`

---

## MemberResource RelationManager

Path: `app/Filament/Admin/Resources/Members/RelationManagers/ObjectsRelationManager.php`

```php
final class MemberObjectsRelationManager extends RelationManager
{
    protected static string $relationship = 'objects';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('memberObjectType.name')->label(__('labels.member_object_type')),
                TextColumn::make('name')->label(__('labels.name')),
                TextColumn::make('start_date')->label(__('labels.start_date'))->date(),
                TextColumn::make('end_date')->label(__('labels.end_date'))->date(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }
}
```

Register in `MemberResource::getRelations()`:

```php
public static function getRelations(): array
{
    return [
        InvoicesRelationManager::make(),
        BillableItemInstancesRelationManager::make(),
        ActivitiesRelationManager::make(),
        MemberObjectsRelationManager::make(), // add this
    ];
}
```

---

## Language Labels

Add to `lang/nl/labels.php`:

```php
'object' => 'Object',
'objects' => 'Objecten',
'object_type' => 'Objecttype',
'object_types' => 'Objecttypes',
```

---

## Tests

Follow project convention: PHPUnit feature tests via `php artisan make:test --phpunit`.

### `tests/Feature/MemberObjectTest.php`

- Can create a `MemberObject` for a member
- `memberObjects()` relation on `Member` returns correct results
- `memberObjectType()` relation returns correct `ObjectType`
- `memberObjectType` with a `billableItem` returns the associated billable item
- `memberObjectType` `anders` has no billable item (`null`)

### `tests/Feature/Filament/MemberObjectTypeResourceTest.php`

- Can list object types
- Can create an object type with billable item
- Can edit an object type
- Can delete an object type

### `tests/Feature/Filament/MemberObjectResourceTest.php`

- Can list member objects
- Can create a member object
- Can edit a member object
- Can delete a member object

### `tests/Feature/Filament/MemberObjectsRelationManagerTest.php`

- Relation manager renders in MemberResource edit page
- Can create an object via the relation manager
- Can delete an object via the relation manager

---

## Implementation Order

1. Check/add `BillPeriod::Once` domain enum case + label
2. Create migrations (`object_types`, `objects`)
3. Create `ObjectType` model + factory
4. Create `MemberObject` model + factory
5. Add `objects()` relation to `Member` model
6. Create `ObjectTypeSeeder` and register in `DatabaseSeeder`
7. Create `ObjectTypeResource` (Filament)
8. Create `MemberObjectResource` (Filament)
9. Create `ObjectsRelationManager` and register in `MemberResource`
10. Add language labels to `lang/nl/labels.php`
11. Run migrations and seeder
12. Write and run tests
