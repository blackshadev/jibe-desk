# Implementation Plan: Cost Centers

## Goal

Introduce **cost centers** as a financial categorization entity. Every invoice line and every billable item instance must be correlated to a cost center so that billing can be automated and reported per cost center.

A cost center has:
- **number** — a human-readable identifier (e.g. `1000`, `2000A`)
- **title** — short name
- **description** — longer explanation

## Data Flow & Automation Chain

The cost center is defined once on the **BillableItem** (the billing definition) and propagates downstream at creation time:

```
BillableItem (cost_center_id)
  └─► BillableItemInstance
        └─► InvoiceLine (cost_center_id copied at invoice generation)
```

This ensures historical accuracy: changing a billable item's cost center only affects future invoice lines, not existing ones.

### Key propagation points

| From | To | Where |
|------|----|-------|
| `BillableItem.cost_center_id` | `BillableItemInstance.cost_center_id` | `BillableItemDbInstanceRepository::add()` / `ensure()` |
| Domain `BillableItem.costCenterId` | `InvoiceLine.cost_center_id` | `InvoiceRepositoryDb::applyLines()` / `create()` |

---

## Phase 1: Database Migrations

### Step 1.1 — Create `cost_centers` table

```bash
./Taskfile artisan make:migration create_cost_centers_table --no-interaction
```

File: `database/migrations/YYYY_MM_DD_HHMMSS_create_cost_centers_table.php`

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create('cost_centers', static function (Blueprint $table): void {
            $table->id();
            $table->string('number')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_centers');
    }
};
```

### Step 1.2 — Add `cost_center_id` to `billable_items`, `billable_item_instances`, and `invoice_lines`

```bash
./Taskfile artisan make:migration add_cost_center_id_to_billable_items_and_instances_and_invoice_lines --no-interaction
```

File: `database/migrations/YYYY_MM_DD_HHMMSS_add_cost_center_id_to_billable_items_and_instances_and_invoice_lines.php`

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::table('billable_items', static function (Blueprint $table): void {
            $table->foreignId('cost_center_id')
                ->constrained('cost_centers');
            $table->index('cost_center_id');
        });


        Schema::table('invoice_lines', static function (Blueprint $table): void {
            $table->foreignId('cost_center_id')
                ->constrained('cost_centers');
            $table->index('cost_center_id');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_lines', static function (Blueprint $table): void {
            $table->dropForeign(['cost_center_id']);
            $table->dropIndex(['cost_center_id']);
            $table->dropColumn('cost_center_id');
        });
=

        Schema::table('billable_items', static function (Blueprint $table): void {
            $table->dropForeign(['cost_center_id']);
            $table->dropIndex(['cost_center_id']);
            $table->dropColumn('cost_center_id');
        });
    }
};
```

Both columns are not nullable and thus cannot be migrated with existing data. This a fresh seeding should occur using `./Taskfile artisan migrate:fresh --seed`.

---

## Phase 2: Domain Layer

### Step 2.1 — Create `CostCenterId` value object

File: `app/Domain/Invoices/Billing/CostCenterId.php`

Follows the exact same pattern as `BillableItemId` (`app/Domain/Invoices/Billing/BillableItemId.php`):

```php
<?php

declare(strict_types=1);

namespace App\Domain\Invoices\Billing;

use App\Domain\NumericId;

final readonly class CostCenterId extends NumericId {}
```

### Step 2.2 — Add `costCenterId` to domain `BillableItem`

File: `app/Domain/Invoices/Billing/BillableItem.php`

Add `CostCenterId $costCenterId` to the constructor:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Invoices\Billing;

use App\Domain\Invoices\CompoundPrice;

final readonly class BillableItem
{
    public function __construct(
        public BillableItemId $id,
        public CompoundPrice $price,
        public float $quantity,
        public string $description,
        public CostCenterId $costCenterId,
    ) {}
}
```


---

## Phase 3: Eloquent Models

### Step 3.1 — Create `CostCenter` model

```bash
./Taskfile artisan make:model CostCenter --no-interaction
```

File: `app/Models/CostCenter.php`

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['number', 'title', 'description'])]
final class CostCenter extends Model
{
    use HasFactory;

    /** @return HasMany<BillableItem, $this> */
    public function billableItems(): HasMany
    {
        return $this->hasMany(BillableItem::class);
    }

    /** @return HasMany<InvoiceLine, $this> */
    public function invoiceLines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }
}
```

### Step 3.2 — Update `BillableItem` model

File: `app/Models/BillableItem.php`

1. Add `cost_center_id` to the `#[Fillable]` attribute
2. Add `costCenter()` BelongsTo relation
3. Update `toInvoiceBillableItem()` to pass the cost center ID to the `InvoiceBillableItem` constructor.

```php
#[Fillable(['description', 'price', 'vat', 'bill_period', 'cost_center_id'])]
final class BillableItem extends Model
{
    // ... existing code ...

    /** @return BelongsTo<CostCenter, $this> */
    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    public function toInvoiceBillableItem(): InvoiceBillableItem
    {
        return new InvoiceBillableItem(
            new BillableItemId($this->id),
            $this->compound_price,
            1.0,
            $this->description,
            new CostCenterId($this->cost_center_id),
        );
    }

    // ... rest of existing code ...
}
```

Add `use App\Domain\Invoices\Billing\CostCenterId;` to the imports.

### Step 3.3 — Update `InvoiceLine` model

File: `app/Models/InvoiceLine.php`

1. Add `cost_center_id` to the `#[Fillable]` attribute
2. Add `costCenter()` BelongsTo relation

```php
#[Fillable(['description', 'vat', 'price', 'quantity', 'member_id', 'billable_item_id', 'cost_center_id'])]
final class InvoiceLine extends Model
{
    // ... existing code ...

    /** @return BelongsTo<CostCenter, $this> */
    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    // ... rest of existing code ...
}
```

---

## Phase 4: Factories

### Step 4.1 — Create `CostCenterFactory`

```bash
./Taskfile artisan make:factory CostCenterFactory --model=CostCenter --no-interaction
```

File: `database/factories/CostCenterFactory.php`

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CostCenter;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<CostCenter>
 */
final class CostCenterFactory extends Factory
{
    /** @return array<string, mixed> */
    #[Override]
    public function definition(): array
    {
        return [
            'number' => fake()->unique()->numerify('####'),
            'title' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
```

### Step 4.2 — Update `BillableItemFactory`

File: `database/factories/BillableItemFactory.php`

Add `cost_center_id` to the definition:

```php
public function definition(): array
{
    $price = fake()->randomFloat(2, 5, 100);

    return [
        'description' => fake()->sentence(),
        'price' => $price,
        'vat' => $price * 0.21,
        'bill_period' => fake()->randomElement(BillPeriod::cases())->value,
        'cost_center_id' => CostCenter::factory(),
    ];
}
```

Add `use App\Models\CostCenter;` to imports.

### Step 4.3 — Update `BillableItemInstanceFactory`

File: `database/factories/BillableItemInstanceFactory.php`

Add `cost_center_id` to the definition, using the associated billable item's cost center:

```php
public function definition(): array
{
    return [
        'member_id' => null,
        'billable_item_id' => BillableItem::factory(),
        'bill_cycle_in_months' => 12,
        'start_date' => fake()->date(),
        'end_date' => null,
        'cost_center_id' => null,
    ];
}

public function afterCreating(BillableItemInstance $instance): void
{
    $instance->cost_center_id = $instance->billableItem->cost_center_id;
    $instance->save();
}
```

> **Note**: In tests that create `BillableItemInstance` with a `BillableItem` that has a cost center, the instance's `cost_center_id` should be set explicitly. The factory leaves it `null` by default so tests can control it. Alternatively, use a `afterCreating` hook to copy from the billable item — but explicit is clearer for test readability.

### Step 4.4 — Update `InvoiceLineFactory`

File: `database/factories/InvoiceLineFactory.php`

Add `cost_center_id` to the definition:

```php
public function definition(): array
{
    $price = fake()->randomFloat(2, 5, 100);

    return [
        'description' => fake()->sentence(),
        'price' => $price,
        'quantity' => fake()->randomFloat(2, 1, 5),
        'vat' => $price * 0.21,
        'cost_center_id' => CostCenter::factory(),
    ];
}
```

Add `use App\Models\CostCenter;` to imports.

---

## Phase 5: Infrastructure — Propagation Logic

### Step 5.1 — Update `InvoiceRepositoryDb`

File: `app/Infrastructure/Invoices/InvoiceRepositoryDb.php`

In both `create()` and `applyLines()`, add `cost_center_id` to the invoice line creation array:

**`create()` method** (around line 52):
```php
->createMany(
    array_map(
        static fn (BillableItem $item) => [
            'description' => $item->description,
            'price' => $item->price->price,
            'vat' => $item->price->vat,
            'quantity' => $item->quantity,
            'billable_item_id' => $item->id->value,
            'cost_center_id' => $item->costCenterId?->value,
        ],
        $invoice->items->items,
    ),
)
```

**`applyLines()` method** (around line 104): same change — add `'cost_center_id' => $item->costCenterId?->value,` to the array.

---

## Phase 6: Filament Resource — CostCenterResource

Follow the exact directory structure pattern used by `MemberObjectTypeResource` and `ExtraMembershipItemResource`.

### Step 6.1 — Create directory structure

```
app/Filament/Admin/Resources/CostCenters/
├── CostCenterResource.php
├── Pages/
│   ├── ListCostCenters.php
│   ├── CreateCostCenter.php
│   └── EditCostCenter.php
├── Schemas/
│   └── CostCenterForm.php
└── Tables/
    └── CostCentersTable.php
```

### Step 6.2 — CostCenterResource

File: `app/Filament/Admin/Resources/CostCenters/CostCenterResource.php`

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\CostCenters;

use App\Filament\Admin\Navigation\NavigationGroup;
use App\Filament\Admin\Resources\CostCenters\Pages\CreateCostCenter;
use App\Filament\Admin\Resources\CostCenters\Pages\EditCostCenter;
use App\Filament\Admin\Resources\CostCenters\Pages\ListCostCenters;
use App\Filament\Admin\Resources\CostCenters\Schemas\CostCenterForm;
use App\Filament\Admin\Resources\CostCenters\Tables\CostCentersTable;
use App\Models\CostCenter;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Override;
use UnitEnum;

final class CostCenterResource extends Resource
{
    protected static bool $isGloballySearchable = false;

    protected static ?string $model = CostCenter::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Banknotes;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Invoicing;

    protected static ?string $recordTitleAttribute = 'title';

    #[Override]
    public static function form(Schema $schema): Schema
    {
        return CostCenterForm::configure($schema);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return CostCentersTable::configure($table);
    }

    #[Override]
    public static function getLabel(): string
    {
        return __('labels.cost_center');
    }

    #[Override]
    public static function getPluralLabel(): string
    {
        return __('labels.cost_centers');
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ListCostCenters::route('/'),
            'create' => CreateCostCenter::route('/create'),
            'edit' => EditCostCenter::route('/{record}/edit'),
        ];
    }
}
```

### Step 6.3 — CostCenterForm

File: `app/Filament/Admin/Resources/CostCenters/Schemas/CostCenterForm.php`

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\CostCenters\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class CostCenterForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('number')
                            ->label(__('labels.number'))
                            ->required()
                            ->unique(ignoreRecord: true),
                        TextInput::make('title')
                            ->label(__('labels.title'))
                            ->required(),
                        Textarea::make('description')
                            ->label(__('labels.description'))
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
```

### Step 6.4 — CostCentersTable

File: `app/Filament/Admin/Resources/CostCenters/Tables/CostCentersTable.php`

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\CostCenters\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class CostCentersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')
                    ->label(__('labels.number'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('title')
                    ->label(__('labels.title'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('description')
                    ->label(__('labels.description'))
                    ->limit(50)
                    ->toggleable(),
            ])
            ->filters([])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
```

### Step 6.5 — Pages

File: `app/Filament/Admin/Resources/CostCenters/Pages/ListCostCenters.php`

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\CostCenters\Pages;

use App\Filament\Admin\Resources\CostCenters\CostCenterResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Override;

final class ListCostCenters extends ListRecords
{
    protected static string $resource = CostCenterResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
```

File: `app/Filament/Admin/Resources/CostCenters/Pages/CreateCostCenter.php`

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\CostCenters\Pages;

use App\Filament\Admin\Resources\CostCenters\CostCenterResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateCostCenter extends CreateRecord
{
    protected static string $resource = CostCenterResource::class;
}
```

File: `app/Filament/Admin/Resources/CostCenters/Pages/EditCostCenter.php`

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\CostCenters\Pages;

use App\Filament\Admin\Resources\CostCenters\CostCenterResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Override;

final class EditCostCenter extends EditRecord
{
    protected static string $resource = CostCenterResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
```

---

## Phase 7: Update Existing Filament Forms — Add Cost Center Select

Add a cost center `Select` to every form that embeds a `billableItem` relationship section. The select uses the `cost_center_id` field on the `billableItem` relationship.

### Step 7.1 — ActivityForm

File: `app/Filament/Admin/Resources/Activities/Schemas/ActivityForm.php`

In the billing `Section` (the one with `->relationship('billableItem')`), add a cost center Select:

```php
use App\Models\CostCenter;
use Filament\Forms\Components\Select;

// Inside the billableItem relationship Section schema array:
Select::make('cost_center_id')
    ->label(__('labels.cost_center'))
    ->relationship('costCenter', 'title')
    ->searchable()
    ->preload()
    ->required(),
```

> **Important**: The `Select::make('cost_center_id')` is inside a `Section::make()->relationship('billableItem')` block. Filament will save it to the `billableItem` relationship model. The `->relationship('costCenter', 'title')` here refers to the `costCenter` relation **on the BillableItem model** (since the section's relationship is `billableItem`). This is the correct nesting — Filament resolves the relationship relative to the section's relationship model.

Wait — actually, Filament's `->relationship()` on a `Select` inside a `Section::make()->relationship('billableItem')` will resolve relative to the **BillableItem** model. So `Select::make('cost_center_id')->relationship('costCenter', 'title')` will look for `BillableItem::costCenter()` which is correct.

However, we need to verify this. If Filament does not support nested relationships this way, the alternative is to use a standalone Select with options:

```php
Select::make('cost_center_id')
    ->label(__('labels.cost_center'))
    ->options(static fn () => CostCenter::query()->orderBy('number')->pluck('title', 'id'))
    ->searchable()
    ->required(),
```

This is safer and doesn't rely on nested relationship resolution. **Use this approach** for all billableItem relationship sections.

### Step 7.2 — Apply to all forms with embedded billableItem

Add the cost center Select to the billing section of these forms:

| File | Section relationship |
|------|---------------------|
| `app/Filament/Admin/Resources/Activities/Schemas/ActivityForm.php` | `billableItem` |
| `app/Filament/Admin/Resources/MemberObjectTypes/Schemas/MemberObjectTypeForm.php` | `billableItem` |
| `app/Filament/Admin/Resources/StorageSpaceLocations/Schemas/StorageSpaceLocationForm.php` | `billableItem` |
| `app/Filament/Admin/Resources/ExtraMembershipItems/Schemas/ExtraMembershipItemForm.php` | `billableItem` |

For each, add inside the `Section::make(__('labels.billing'))->relationship('billableItem')` schema array:

```php
Select::make('cost_center_id')
    ->label(__('labels.cost_center'))
    ->options(fn () => CostCenter::query()->orderBy('number')->pluck('title', 'id'))
    ->searchable()
    ->preload()
    ->required(),
```

### Step 7.3 — MembershipForm (two billable items: adult + kids)

File: `app/Filament/Admin/Resources/Memberships/Schemas/MembershipForm.php`

Add the cost center Select to **both** the `adultBillableItem` and `kidsBillableItem` sections:

```php
// In adultBillableItem section:
Select::make('cost_center_id')
    ->label(__('labels.cost_center'))
    ->options(fn () => CostCenter::query()->orderBy('number')->pluck('title', 'id'))
    ->searchable()
    ->preload()
    ->required(),

// In kidsBillableItem section: (same field)
Select::make('cost_center_id')
    ->label(__('labels.cost_center'))
    ->options(fn () => CostCenter::query()->orderBy('number')->pluck('title', 'id'))
    ->searchable()
    ->preload()
    ->required(),
```

### Step 7.4 — InvoiceForm (manual invoice lines)

File: `app/Filament/Admin/Resources/Invoices/Schemas/InvoiceForm.php`

In the `Repeater::make('lines')` schema, add a cost center Select so manually created invoice lines can be assigned a cost center:

```php
Select::make('cost_center_id')
    ->label(__('labels.cost_center'))
    ->options(fn () => CostCenter::query()->orderBy('number')->pluck('title', 'id'))
    ->searchable()
    ->preload()
    ->required(),
```

Also update the `mutateRelationshipDataBeforeSaveUsing` and `mutateRelationshipDataBeforeCreateUsing` callbacks to preserve `cost_center_id` (it should pass through automatically since it's in the form data, but verify).

---

## Phase 8: Update Existing Filament Tables — Show Cost Center

### Step 8.1 — BillableItemInstancesRelationManager

File: `app/Filament/Admin/Resources/Members/RelationManagers/BillableItemInstancesRelationManager.php`

Add a cost center column to the table:

```php
TextColumn::make('costCenter.title')
    ->label(__('labels.cost_center'))
    ->toggleable(),
```

### Step 8.2 — InvoiceForm repeater item label (optional enhancement)

File: `app/Filament/Admin/Resources/Invoices/Schemas/InvoiceForm.php`

Optionally include the cost center in the repeater item label:

```php
->itemLabel(static fn (array $state) => sprintf(
    '(%.2fx) %s %s',
    $state['quantity'],
    $state['description'],
    PriceFormatter::format((float) $state['price']),
))
```

(No change needed — the cost center is shown as a field inside the repeater.)

---

## Phase 9: Policy

### Step 9.1 — Create CostCenterPolicy

```bash
./Taskfile artisan make:policy CostCenterPolicy --no-interaction
```

File: `app/Policies/CostCenterPolicy.php`

```php
<?php

declare(strict_types=1);

namespace App\Policies;

final class CostCenterPolicy extends ResourcePolicy
{
    protected static function permissionPrefix(): string
    {
        return 'cost_centers';
    }
}
```

### Step 9.2 — Register policy

File: `app/Providers/AppServiceProvider.php` (or wherever policies are registered — check existing pattern)

> **Check**: Look for a `Policy` map in `AppServiceProvider` or rely on Laravel's automatic policy resolution (model `CostCenter` → `CostCenterPolicy`). Laravel auto-resolves policies by convention, so no explicit registration is needed if the naming convention is followed.

---

## Phase 10: Permissions

### Step 10.1 — Add cost center permissions to `ResourcePermission` enum

File: `app/Domain/Authorization/ResourcePermission.php`

Add after the BillableItemInstances block:

```php
// Cost Centers
case ViewAnyCostCenters = 'view_any_cost_centers';
case ViewCostCenters = 'view_cost_centers';
case CreateCostCenters = 'create_cost_centers';
case UpdateCostCenters = 'update_cost_centers';
case DeleteCostCenters = 'delete_cost_centers';
case DeleteAnyCostCenters = 'delete_any_cost_centers';
```

### Step 10.2 — Assign permissions in `RolePermissionSeeder`

File: `database/seeders/RolePermissionSeeder.php`

Cost centers are financial configuration. The `financial_administration` role should have full CRUD, and `member_administration` should have view access (to see cost centers on billable items).

In `seedFinancialAdministration()`:
```php
$this->allPermissionsFor('cost_centers'),
```

In `seedMemberAdministration()`:
```php
$this->viewPermissionsFor('cost_centers'),
```

> Cost centers are also visible in the Invoicing navigation group. Technical admin gets full access because cost centers are configuration items that may need to be managed by technical staff.

---

## Phase 11: Labels (Dutch translations)

File: `lang/nl/labels.php`

Add these entries (e.g., after the `billable_item_instances` entry around line 82):

```php
'cost_center' => 'Kostenplaats',
'cost_centers' => 'Kostenplaatsen',
'number' => 'Nummer',
'title' => 'Titel',
```

> **Note**: `description` already exists at line 41. `number` may need to be added if not already present (check — it's not in the current labels file).

---

## Phase 12: Tests

### Step 12.1 — CostCenter model unit test

```bash
./Taskfile artisan make:test --phpunit --unit CostCenterTest
```

Basic model tests: factory creates valid model, relationships work.

### Step 12.2 — CostCenterResource feature test

```bash
./Taskfile artisan make:test --phpunit CostCenterResourceTest
```

File: `tests/Feature/Filament/CostCenters/CostCenterResourceTest.php`

Follow the pattern from `tests/Feature/Filament/StorageSpaceLocations/StorageSpaceLocationResourceTest.php`:
- `test_can_list_cost_centers`
- `test_can_create_cost_center`
- `test_can_edit_cost_center`
- `test_can_delete_cost_center`

Use `WithAuthorizedUser` trait.

### Step 12.3 — Propagation test: BillableItemInstance → InvoiceLine

```bash
./Taskfile artisan make:test --phpunit InvoiceLineCostCenterPropagationTest
```

File: `tests/Feature/Infrastructure/Invoices/InvoiceLineCostCenterPropagationTest.php`

Test that when invoice lines are generated via `BillableItemsViewDbRepository` + `InvoiceRepositoryDb`, the `cost_center_id` is propagated from the `BillableItemInstance` to the `InvoiceLine`.

```php
public function test_apply_lines_copies_cost_center_id_from_instance(): void
{
    $costCenter = CostCenter::factory()->create();
    $billable = BillableItem::factory()->create(['bill_period' => 'monthly', 'cost_center_id' => $costCenter->id]);
    $membership = Membership::factory()->create();
    $member = Member::factory()->createQuietly(['membership_id' => $membership->id]);

    BillableItemInstance::factory()->create([
        'member_id' => $member->id,
        'billable_item_id' => $billable->id,
        'bill_cycle_in_months' => 1,
        'start_date' => '2026-01-01',
        'end_date' => null,
    ]);

    $when = new DateTimeImmutable('2026-05-15');
    $viewRepo = new BillableItemsViewDbRepository();
    $items = $viewRepo->listBillableItemsForMember($when, MemberId::create($member->id));

    $invoiceRepo = new InvoiceRepositoryDb(new InvoiceNumberDbRepository(...));
    $invoiceId = $invoiceRepo->applyLines(new ApplyInvoiceLines(
        MemberId::create($member->id),
        $when,
        $items,
    ));

    $line = InvoiceLine::query()->where('invoice_id', $invoiceId->invoiceId->value)->first();

    static::assertSame($costCenter->id, $line->cost_center_id);
}
```

### Step 12.4 — Update existing tests

Update tests that construct domain `BillableItem` without a cost center — they should still work because `costCenterId` defaults to `null`.

Update `tests/Feature/Infrastructure/Invoices/BillableItemsViewDbRepositoryTest.php` — the existing tests create `BillableItem` and `BillableItemInstance` without cost centers. These should still pass (nullable column). Add a new test case verifying cost center propagation through the view repository.

Update `tests/Unit/Domain/Invoices/InvoiceGeneratorImplTest.php` — the `BillableItem` constructor calls may need updating if the test asserts on constructor parameters. Since `costCenterId` has a default value, existing calls should work unchanged.

### Step 12.5 — Run tests

```bash
# Run the new tests
./Taskfile artisan test --compact --filter=CostCenter
./Taskfile artisan test --compact --filter=CostCenterPropagation
./Taskfile artisan test --compact --filter=InvoiceLineCostCenter

# Run affected existing tests
./Taskfile artisan test --compact tests/Feature/Infrastructure/Invoices/BillableItemsViewDbRepositoryTest.php
./Taskfile artisan test --compact tests/Unit/Domain/Invoices/InvoiceGeneratorImplTest.php
./Taskfile artisan test --compact tests/Unit/Domain/Invoices/BillableItemListTest.php

# Then ask user if they want to run the full suite
```

---

## Summary of all files to create/modify

### New files
| File | Purpose |
|------|---------|
| `database/migrations/____create_cost_centers_table.php` | Cost centers table |
| `database/migrations/____add_cost_center_id_to_*.php` | FK on billable_items, billable_item_instances, invoice_lines |
| `app/Domain/Invoices/Billing/CostCenterId.php` | Domain ID value object |
| `app/Models/CostCenter.php` | Eloquent model |
| `database/factories/CostCenterFactory.php` | Factory |
| `app/Policies/CostCenterPolicy.php` | Policy |
| `app/Filament/Admin/Resources/CostCenters/CostCenterResource.php` | Filament resource |
| `app/Filament/Admin/Resources/CostCenters/Pages/ListCostCenters.php` | List page |
| `app/Filament/Admin/Resources/CostCenters/Pages/CreateCostCenter.php` | Create page |
| `app/Filament/Admin/Resources/CostCenters/Pages/EditCostCenter.php` | Edit page |
| `app/Filament/Admin/Resources/CostCenters/Schemas/CostCenterForm.php` | Form schema |
| `app/Filament/Admin/Resources/CostCenters/Tables/CostCentersTable.php` | Table schema |
| `tests/Feature/Filament/CostCenters/CostCenterResourceTest.php` | Feature test |
| `tests/Feature/Infrastructure/Invoices/BillableItemInstanceCostCenterPropagationTest.php` | Propagation test |
| `tests/Feature/Infrastructure/Invoices/InvoiceLineCostCenterPropagationTest.php` | Propagation test |

### Modified files
| File | Change |
|------|--------|
| `app/Domain/Invoices/Billing/BillableItem.php` | Add `?CostCenterId $costCenterId` to constructor |
| `app/Models/BillableItem.php` | Add `cost_center_id` to Fillable, add `costCenter()` relation, update `toInvoiceBillableItem()` |
| `app/Models/BillableItemInstance.php` | Add `cost_center_id` to Fillable, add `costCenter()` relation |
| `app/Models/InvoiceLine.php` | Add `cost_center_id` to Fillable, add `costCenter()` relation |
| `app/Infrastructure/Invoices/Billing/BillableItemDbInstanceRepository.php` | Copy `cost_center_id` in `add()` and `ensure()` |
| `app/Infrastructure/Invoices/Billing/BillableItemsViewDbRepository.php` | Pass instance `cost_center_id` to `toInvoiceBillableItem()` |
| `app/Infrastructure/Invoices/InvoiceRepositoryDb.php` | Set `cost_center_id` on invoice line creation in `create()` and `applyLines()` |
| `database/factories/BillableItemFactory.php` | Add `cost_center_id` |
| `database/factories/BillableItemInstanceFactory.php` | Add `cost_center_id` |
| `database/factories/InvoiceLineFactory.php` | Add `cost_center_id` |
| `app/Filament/Admin/Resources/Activities/Schemas/ActivityForm.php` | Add cost center Select |
| `app/Filament/Admin/Resources/MemberObjectTypes/Schemas/MemberObjectTypeForm.php` | Add cost center Select |
| `app/Filament/Admin/Resources/StorageSpaceLocations/Schemas/StorageSpaceLocationForm.php` | Add cost center Select |
| `app/Filament/Admin/Resources/ExtraMembershipItems/Schemas/ExtraMembershipItemForm.php` | Add cost center Select |
| `app/Filament/Admin/Resources/Memberships/Schemas/MembershipForm.php` | Add cost center Select (adult + kids) |
| `app/Filament/Admin/Resources/Invoices/Schemas/InvoiceForm.php` | Add cost center Select to lines repeater |
| `app/Filament/Admin/Resources/Members/RelationManagers/BillableItemInstancesRelationManager.php` | Add cost center column |
| `app/Domain/Authorization/ResourcePermission.php` | Add cost_centers permissions |
| `database/seeders/RolePermissionSeeder.php` | Assign cost_centers permissions to roles |
| `lang/nl/labels.php` | Add cost center labels |

---

## Execution order

1. **Migrations** (Phase 1) — creates tables, run `./Taskfile artisan migrate`
2. **Domain layer** (Phase 2) — `CostCenterId`, update domain `BillableItem`
3. **Models** (Phase 3) — `CostCenter` model, update `BillableItem`, `BillableItemInstance`, `InvoiceLine`
4. **Factories** (Phase 4) — `CostCenterFactory`, update existing factories
5. **Infrastructure** (Phase 5) — propagation logic in repositories
6. **Filament resource** (Phase 6) — `CostCenterResource` with pages, form, table
7. **Filament forms** (Phase 7) — add cost center Select to all billableItem forms + InvoiceForm
8. **Filament tables** (Phase 8) — add cost center column to relation manager
9. **Policy** (Phase 9) — `CostCenterPolicy`
10. **Permissions** (Phase 10) — `ResourcePermission` enum + `RolePermissionSeeder`
11. **Labels** (Phase 11) — Dutch translations
12. **Tests** (Phase 12) — write and run tests
