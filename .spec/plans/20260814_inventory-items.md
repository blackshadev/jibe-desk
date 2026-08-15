# Implementation Plan: Inventory (valuable items & big purchases)

## Goal

Add a new **Inventory** domain to track the association's valuable items and big purchases (Dutch: *inventaris*). An inventory item records:

- **date** — purchase date
- **amount** — purchase amount (single monetary value, no VAT split)
- **write-off period in years** — depreciation period
- **receipt** — uploaded image of the receipt (proof of purchase)
- **category** — a separate resource that groups inventory items (e.g. "Windsurf materiaal", "Boot materialen", "Apparaten")
- **purchase order** (optional) — link to an existing PurchaseOrder; selecting one prefills empty fields (date, amount, receipt) from the PO

Two Filament resources are created:

1. **InventoryItemResource** — the items themselves (date, amount, write-off period, receipt, category).
2. **InventoryCategoryResource** — the categories used to group items. A category cannot be deleted while it still has items.

## Domain decisions & assumptions

| Decision | Choice | Notes |
|----------|--------|-------|
| Model / table naming | `InventoryItem` / `inventory_items`, `InventoryCategory` / `inventory_categories` | Follows singular/plural snake_case convention |
| Navigation group | New `NavigationGroup::Inventory` (`inventory` → "Inventaris") | Mirrors `Activities` / `Rental` (each domain owns its group) |
| `amount` semantics | Single `decimal(10,2)` column, no VAT | The user asked for "amount", not a `CompoundPrice` |
| Write-off period type | `unsignedSmallInteger` (whole years) | Label includes "(jaren)" |
| Receipt storage | Follows **PurchaseOrder** `image_path` pattern exactly | `FileUpload` → `receipt_path`, `local` private disk, signed preview URLs, `InventoryItemObserver` cleans up on delete |
| Receipt file type | Images only (`->image()`) | Matches PurchaseOrder. If PDFs are needed, drop `->image()` and add `->acceptedFileTypes([...])` + swap `ImageColumn` for a download link |
| Category required? | Yes (`->required()`, non-nullable FK) | User asked to group inventory; grouping without category is not useful |
| Description/name field | **Not added** | User listed exactly 5 fields. If a human-readable name is wanted, add a nullable `name`/`description` after confirming with the user |
| Permissions | `financial_administration`  get full CRUD on both resources | Inventory is financial config/records |
| Purchase order link | Nullable FK `purchase_order_id` → `purchase_orders` | Optional; selecting a PO prefills empty `date`, `amount`, `receipt_path` from the PO. Receipt file is **copied** from PO's `image_path` to `inventory-items/` directory (avoids shared-file deletion issues) |

---

## Phase 1: Database Migrations

### Step 1.1 — Create `inventory_categories` table

```bash
./Taskfile artisan make:migration create_inventory_categories_table --no-interaction
```

File: `database/migrations/YYYY_MM_DD_HHMMSS_create_inventory_categories_table.php`

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create('inventory_categories', static function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_categories');
    }
};
```

### Step 1.2 — Create `inventory_items` table

```bash
./Taskfile artisan make:migration create_inventory_items_table --no-interaction
```

File: `database/migrations/YYYY_MM_DD_HHMMSS_create_inventory_items_table.php`

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create('inventory_items', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_category_id')->constrained('inventory_categories');
            $table->string('description');
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->date('date');
            $table->decimal('amount', 10, 2);
            $table->unsignedSmallInteger('write_off_period_years');
            $table->string('receipt_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
```

> `->constrained()` (no cascade) means deleting a category that still has items throws an FK error. This is intentional data protection; the Filament UI additionally hides the delete action when items exist (Phase 9).

---

## Phase 2: Eloquent Models

### Step 2.1 — `InventoryCategory` model

```bash
./Taskfile artisan make:model InventoryCategory --no-interaction
```

File: `app/Models/InventoryCategory.php`

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name'])]
final class InventoryCategory extends Model
{
    use HasFactory;

    /** @return HasMany<InventoryItem, $this> */
    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }
}
```

### Step 2.2 — `InventoryItem` model

```bash
./Taskfile artisan make:model InventoryItem --no-interaction
```

File: `app/Models/InventoryItem.php`

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Observers\InventoryItemObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Guarded;use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

#[Guarded(['id', 'created_at', 'updated_at'])]
#[ObservedBy([InventoryItemObserver::class])]
final class InventoryItem extends Model
{
    use HasFactory;

    /** @return BelongsTo<InventoryCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(InventoryCategory::class, 'inventory_category_id');
    }

    /** @return BelongsTo<PurchaseOrder, $this> */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'amount' => 'decimal:2',
            'write_off_period_years' => 'integer',
        ];
    }
}
```

### Step 2.3 — `InventoryItemObserver` (receipt file cleanup)

File: `app/Observers/InventoryItemObserver.php`

```php
<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\InventoryItem;
use Illuminate\Support\Facades\Storage;

final class InventoryItemObserver
{
    public function deleted(InventoryItem $inventoryItem): void
    {
        if ($inventoryItem->receipt_path !== null) {
            Storage::disk('local')->delete($inventoryItem->receipt_path);
        }
    }
}
```

---

## Phase 3: Factories

### Step 3.1 — `InventoryCategoryFactory`

```bash
./Taskfile artisan make:factory InventoryCategoryFactory --model=InventoryCategory --no-interaction
```

File: `database/factories/InventoryCategoryFactory.php`

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\InventoryCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<InventoryCategory>
 */
final class InventoryCategoryFactory extends Factory
{
    /** @return array<string, mixed> */
    #[Override]
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
        ];
    }
}
```

### Step 3.2 — `InventoryItemFactory`

```bash
./Taskfile artisan make:factory InventoryItemFactory --model=InventoryItem --no-interaction
```

File: `database/factories/InventoryItemFactory.php`

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<InventoryItem>
 */
final class InventoryItemFactory extends Factory
{
    /** @return array<string, mixed> */
    #[Override]
    public function definition(): array
    {
        return [
            'inventory_category_id' => InventoryCategory::factory(),
            'description' => fake()->sentence(),
            'date' => fake()->dateTimeBetween('-5 years', 'now'),
            'amount' => fake()->randomFloat(2, 50, 5000),
            'write_off_period_years' => fake()->numberBetween(1, 10),
        ];
    }
}
```

---

## Phase 4: Policies

### Step 4.1 — `InventoryItemPolicy`

File: `app/Policies/InventoryItemPolicy.php`

```php
<?php

declare(strict_types=1);

namespace App\Policies;

use Override;

final class InventoryItemPolicy extends ResourcePolicy
{
    #[Override]
    protected static function permissionPrefix(): string
    {
        return 'inventory_items';
    }
}
```

### Step 4.2 — `InventoryCategoryPolicy`

File: `app/Policies/InventoryCategoryPolicy.php`

```php
<?php

declare(strict_types=1);

namespace App\Policies;

use Override;

final class InventoryCategoryPolicy extends ResourcePolicy
{
    #[Override]
    protected static function permissionPrefix(): string
    {
        return 'inventory_categories';
    }
}
```

> Policies are auto-discovered by convention (`InventoryItem` → `InventoryItemPolicy`), no explicit registration needed (confirmed by existing `CostCenterPolicy` / `StorageSpacePolicy` pattern).

---

## Phase 5: Permissions

### Step 5.1 — Add enum cases to `ResourcePermission`

File: `app/Domain/Authorization/ResourcePermission.php`

Append at the end of the enum (after the Banking Transactions block):

```php
// Inventory Items
case ViewAnyInventoryItems = 'view_any_inventory_items';
case ViewInventoryItems = 'view_inventory_items';
case CreateInventoryItems = 'create_inventory_items';
case UpdateInventoryItems = 'update_inventory_items';
case DeleteInventoryItems = 'delete_inventory_items';
case DeleteAnyInventoryItems = 'delete_any_inventory_items';

// Inventory Categories
case ViewAnyInventoryCategories = 'view_any_inventory_categories';
case ViewInventoryCategories = 'view_inventory_categories';
case CreateInventoryCategories = 'create_inventory_categories';
case UpdateInventoryCategories = 'update_inventory_categories';
case DeleteInventoryCategories = 'delete_inventory_categories';
case DeleteAnyInventoryCategories = 'delete_any_inventory_categories';
```

### Step 5.2 — Assign permissions in `RolePermissionSeeder`

File: `database/seeders/RolePermissionSeeder.php`

In `seedFinancialAdministration()`, add to the `$permissions` array:

```php
$this->allPermissionsFor('inventory_items'),
$this->allPermissionsFor('inventory_categories'),
```

## Phase 7: Filament Resources — InventoryItemResource

Directory structure:

```
app/Filament/Admin/Resources/InventoryItems/
├── InventoryItemResource.php
├── Pages/
│   ├── ListInventoryItems.php
│   ├── CreateInventoryItem.php
│   └── EditInventoryItem.php
├── Schemas/
│   └── InventoryItemForm.php
└── Tables/
    └── InventoryItemsTable.php
```

### Step 7.1 — `InventoryItemResource`

File: `app/Filament/Admin/Resources/InventoryItems/InventoryItemResource.php`

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\InventoryItems;

use App\Filament\Admin\Navigation\NavigationGroup;
use App\Filament\Admin\Resources\InventoryItems\Pages\CreateInventoryItem;
use App\Filament\Admin\Resources\InventoryItems\Pages\EditInventoryItem;
use App\Filament\Admin\Resources\InventoryItems\Pages\ListInventoryItems;
use App\Filament\Admin\Resources\InventoryItems\Schemas\InventoryItemForm;
use App\Filament\Admin\Resources\InventoryItems\Tables\InventoryItemsTable;
use App\Models\InventoryItem;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Override;
use UnitEnum;

final class InventoryItemResource extends Resource
{
    #[Override]
    protected static bool $isGloballySearchable = false;

    #[Override]
    protected static ?string $model = InventoryItem::class;

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::ArchiveBox;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Bookkeeping;

    #[Override]
    public static function form(Schema $schema): Schema
    {
        return InventoryItemForm::configure($schema);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return InventoryItemsTable::configure($table);
    }

    #[Override]
    public static function getLabel(): string
    {
        return __('labels.inventory_item');
    }

    #[Override]
    public static function getPluralLabel(): string
    {
        return __('labels.inventory_items');
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ListInventoryItems::route('/'),
            'create' => CreateInventoryItem::route('/create'),
            'edit' => EditInventoryItem::route('/{record}/edit'),
        ];
    }
}
```

### Step 7.2 — `InventoryItemForm`

File: `app/Filament/Admin/Resources/InventoryItems/Schemas/InventoryItemForm.php`

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\InventoryItems\Schemas;

use App\Models\PurchaseOrder;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;

final class InventoryItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        Select::make('purchase_order_id')
                            ->label(__('labels.purchase_order'))
                            ->relationship('purchaseOrder', 'description')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(static function (?int $state, Set $set): void {
                                if ($state === null) {
                                    return;
                                }

                                $po = PurchaseOrder::find($state);
                                if ($po === null) {
                                    return;
                                }

                                $set('description', $po->description);
                                $set('date', $po->date->format('Y-m-d'));
                                $set('amount', $po->total->price);

                                if ($po->image_path !== null) {
                                    $sourcePath = $po->image_path;
                                    $filename = pathinfo($sourcePath, PATHINFO_BASENAME);
                                    $newPath = 'inventory-items/' . $filename;

                                    if (Storage::disk('local')->exists($sourcePath)) {
                                        Storage::disk('local')->copy($sourcePath, $newPath);
                                        $set('receipt_path', $newPath);
                                    }
                                }
                            })
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->label(__('labels.description'))
                            ->columnSpanFull(),
                        DatePicker::make('date')
                            ->label(__('labels.date'))
                            ->native(false)
                            ->required(),
                        Select::make('inventory_category_id')
                            ->label(__('labels.category'))
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('amount')
                            ->label(__('labels.amount'))
                            ->numeric()
                            ->prefix('€')
                            ->required(),
                        TextInput::make('write_off_period_years')
                            ->label(__('labels.write_off_period_years'))
                            ->numeric()
                            ->required(),
                        FileUpload::make('receipt_path')
                            ->label(__('labels.receipt'))
                            ->image()
                            ->imagePreviewHeight('250')
                            ->directory('inventory-items')
                            ->disk('local')
                            ->visibility('private')
                            ->previewable()
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
```

> Date field follows the **Activity** pattern (`DatePicker` + model `date` cast, no `->format()` override) to avoid date-format ambiguity in tests.

> **Prefill logic**: When a purchase order is selected, the `afterStateUpdated` callback unconditionally sets fields from the PO: `description` ← PO.description, `date` ← PO.date, `amount` ← PO.total.price (excludes VAT), `receipt_path` ← copy of PO's `image_path`. The receipt file is **copied** (not moved) from `purchase-orders/` to `inventory-items/` so both records maintain independent files. Filament's FileUpload automatically deletes the old file when a new one is uploaded, so no manual cleanup is needed if the user replaces the prefilled receipt.

### Step 7.3 — `InventoryItemsTable`

File: `app/Filament/Admin/Resources/InventoryItems/Tables/InventoryItemsTable.php`

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\InventoryItems\Tables;

use App\Domain\Invoices\Formatters\PriceFormatter;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class InventoryItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('date')
                    ->label(__('labels.date'))
                    ->sortable()
                    ->date(),
                TextColumn::make('description')
                    ->label(__('labels.description'))
                    ->searchable()
                    ->limit(50)
                    ->toggleable(),
                TextColumn::make('category.name')
                    ->label(__('labels.category'))
                    ->sortable()
                    ->searchable(),
                TextColumn::make('amount')
                    ->label(__('labels.amount'))
                    ->formatStateUsing(PriceFormatter::format(...))
                    ->sortable()
                    ->alignEnd(),
                TextColumn::make('write_off_period_years')
                    ->label(__('labels.write_off_period_years'))
                    ->sortable()
                    ->numeric(),
            ])
            ->filters([
                SelectFilter::make('inventory_category_id')
                    ->label(__('labels.category'))
                    ->relationship('category', 'name'),
            ], FiltersLayout::BeforeContent)
            ->defaultSort('date', 'desc')
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
}
```

### Step 7.4 — Pages

File: `app/Filament/Admin/Resources/InventoryItems/Pages/ListInventoryItems.php`

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\InventoryItems\Pages;

use App\Filament\Admin\Resources\InventoryItems\InventoryItemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Override;

final class ListInventoryItems extends ListRecords
{
    #[Override]
    protected static string $resource = InventoryItemResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
```

File: `app/Filament/Admin/Resources/InventoryItems/Pages/CreateInventoryItem.php`

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\InventoryItems\Pages;

use App\Filament\Admin\Resources\InventoryItems\InventoryItemResource;
use Filament\Resources\Pages\CreateRecord;
use Override;

final class CreateInventoryItem extends CreateRecord
{
    #[Override]
    protected static string $resource = InventoryItemResource::class;
}
```

File: `app/Filament/Admin/Resources/InventoryItems/Pages/EditInventoryItem.php`

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\InventoryItems\Pages;

use App\Filament\Admin\Resources\InventoryItems\InventoryItemResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Override;

final class EditInventoryItem extends EditRecord
{
    #[Override]
    protected static string $resource = InventoryItemResource::class;

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

## Phase 8: Filament Resources — InventoryCategoryResource

Directory structure:

```
app/Filament/Admin/Resources/InventoryCategories/
├── InventoryCategoryResource.php
├── Pages/
│   ├── ListInventoryCategories.php
│   ├── CreateInventoryCategory.php
│   └── EditInventoryCategory.php
├── Schemas/
│   └── InventoryCategoryForm.php
└── Tables/
    └── InventoryCategoriesTable.php
```

### Step 8.1 — `InventoryCategoryResource`

File: `app/Filament/Admin/Resources/InventoryCategories/InventoryCategoryResource.php`

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\InventoryCategories;

use App\Filament\Admin\Navigation\NavigationGroup;
use App\Filament\Admin\Resources\InventoryCategories\Pages\CreateInventoryCategory;
use App\Filament\Admin\Resources\InventoryCategories\Pages\EditInventoryCategory;
use App\Filament\Admin\Resources\InventoryCategories\Pages\ListInventoryCategories;
use App\Filament\Admin\Resources\InventoryCategories\Schemas\InventoryCategoryForm;
use App\Filament\Admin\Resources\InventoryCategories\Tables\InventoryCategoriesTable;
use App\Models\InventoryCategory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Override;
use UnitEnum;

final class InventoryCategoryResource extends Resource
{
    #[Override]
    protected static bool $isGloballySearchable = false;

    #[Override]
    protected static ?string $model = InventoryCategory::class;

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::Tag;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Bookkeeping;

    #[Override]
    protected static ?string $recordTitleAttribute = 'name';

    #[Override]
    public static function form(Schema $schema): Schema
    {
        return InventoryCategoryForm::configure($schema);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return InventoryCategoriesTable::configure($table);
    }

    #[Override]
    public static function getLabel(): string
    {
        return __('labels.inventory_category');
    }

    #[Override]
    public static function getPluralLabel(): string
    {
        return __('labels.inventory_categories');
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ListInventoryCategories::route('/'),
            'create' => CreateInventoryCategory::route('/create'),
            'edit' => EditInventoryCategory::route('/{record}/edit'),
        ];
    }
}
```

### Step 8.2 — `InventoryCategoryForm`

File: `app/Filament/Admin/Resources/InventoryCategories/Schemas/InventoryCategoryForm.php`

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\InventoryCategories\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class InventoryCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        TextInput::make('name')
                            ->label(__('labels.name'))
                            ->required()
                            ->unique(ignoreRecord: true),
                    ]),
            ]);
    }
}
```

### Step 8.3 — `InventoryCategoriesTable`

File: `app/Filament/Admin/Resources/InventoryCategories/Tables/InventoryCategoriesTable.php`

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\InventoryCategories\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class InventoryCategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('labels.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('inventoryItems_count')
                    ->label(__('labels.inventory_items'))
                    ->counts('inventoryItems')
                    ->numeric()
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

### Step 8.4 — Pages

File: `app/Filament/Admin/Resources/InventoryCategories/Pages/ListInventoryCategories.php`

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\InventoryCategories\Pages;

use App\Filament\Admin\Resources\InventoryCategories\InventoryCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Override;

final class ListInventoryCategories extends ListRecords
{
    #[Override]
    protected static string $resource = InventoryCategoryResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
```

File: `app/Filament/Admin/Resources/InventoryCategories/Pages/CreateInventoryCategory.php`

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\InventoryCategories\Pages;

use App\Filament\Admin\Resources\InventoryCategories\InventoryCategoryResource;
use Filament\Resources\Pages\CreateRecord;
use Override;

final class CreateInventoryCategory extends CreateRecord
{
    #[Override]
    protected static string $resource = InventoryCategoryResource::class;
}
```

File: `app/Filament/Admin/Resources/InventoryCategories/Pages/EditInventoryCategory.php`

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\InventoryCategories\Pages;

use App\Filament\Admin\Resources\InventoryCategories\InventoryCategoryResource;
use App\Models\InventoryCategory;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Override;

final class EditInventoryCategory extends EditRecord
{
    #[Override]
    protected static string $resource = InventoryCategoryResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(static fn (InventoryCategory $record): bool => $record->inventoryItems()->count() === 0),
        ];
    }
}
```

> Mirrors `EditStorageSpaceLocation`'s delete-visible guard: a category with items cannot be deleted from the UI.

---

## Phase 9: Labels (Dutch translations)

File: `lang/nl/labels.php`

Add (e.g. near the `cost_center` block or the `storage_space` block):

```php
'inventory_item' => 'Inventarisitem',
'inventory_items' => 'Inventarisitems',
'inventory_category' => 'Inventariscategorie',
'inventory_categories' => 'Inventariscategorieën',
'amount' => 'Bedrag',
'category' => 'Categorie',
'receipt' => 'Bon',
'write_off_period_years' => 'Afschrijvingsperiode (jaren)',
```

And in the `navigation_groups` array (Phase 6.2):

```php
'inventory' => 'Inventaris',
```

> `date`, `name`, and `description` labels already exist.

---

## Phase 10: Seeders

### Step 10.1 — `InventoryCategorySeeder`

File: `database/seeders/InventoryCategorySeeder.php`

```php
<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\InventoryCategory;
use Illuminate\Database\Seeder;

final class InventoryCategorySeeder extends Seeder
{
    public function run(): void
    {
        InventoryCategory::factory()->createMany([
            ['name' => 'Windsurf materiaal'],
            ['name' => 'Boot materialen'],
            ['name' => 'Apparaten'],
        ]);
    }
}
```

### Step 10.2 — Register in `DatabaseSeeder`

File: `database/seeders/DatabaseSeeder.php`

Add after the `StorageSpaceLocationSeeder` call:

```php
$this->call(InventoryCategorySeeder::class);
```

---

## Phase 11: Tests

### Step 11.1 — `InventoryItemResourceTest`

```bash
./Taskfile artisan make:test --phpunit InventoryItemResourceTest
```

File: `tests/Feature/Filament/InventoryItems/InventoryItemResourceTest.php`

Follow the pattern from `tests/Feature/Filament/CostCenters/CostCenterResourceTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\InventoryItems;

use App\Filament\Admin\Resources\InventoryItems\Pages\CreateInventoryItem;
use App\Filament\Admin\Resources\InventoryItems\Pages\EditInventoryItem;
use App\Filament\Admin\Resources\InventoryItems\Pages\ListInventoryItems;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use Livewire\Livewire;
use Tests\Concerns\WithAuthorizedUser;
use Tests\FeatureTestCase;

final class InventoryItemResourceTest extends FeatureTestCase
{
    use WithAuthorizedUser;

    public function test_can_list_inventory_items(): void
    {
        $this->withAuthorizedUser();

        InventoryItem::factory()->createOne();
        InventoryItem::factory()->createOne();

        Livewire::test(ListInventoryItems::class)
            ->assertCanSeeTableRecords(InventoryItem::all());
    }

    public function test_can_create_inventory_item(): void
    {
        $this->withAuthorizedUser();

        $category = InventoryCategory::factory()->createOne();

        Livewire::test(CreateInventoryItem::class)
            ->fillForm([
                'inventory_category_id' => $category->id,
                'description' => 'Nieuwe zeil',
                'date' => '2026-06-15',
                'amount' => 499.99,
                'write_off_period_years' => 5,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('inventory_items', [
            'inventory_category_id' => $category->id,
            'description' => 'Nieuwe zeil',
            'amount' => 499.99,
            'write_off_period_years' => 5,
        ]);
    }

    public function test_can_edit_inventory_item(): void
    {
        $this->withAuthorizedUser();

        $item = InventoryItem::factory()->createOne(['amount' => 100]);

        Livewire::test(EditInventoryItem::class, ['record' => $item->id])
            ->fillForm([
                'amount' => 200,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('inventory_items', [
            'id' => $item->id,
            'amount' => 200,
        ]);
    }

    public function test_can_delete_inventory_item(): void
    {
        $this->withAuthorizedUser();

        $item = InventoryItem::factory()->createOne();

        Livewire::test(EditInventoryItem::class, ['record' => $item->id])
            ->assertActionEnabled('delete')
            ->callAction('delete');

        $this->assertDatabaseMissing('inventory_items', [
            'id' => $item->id,
        ]);
    }

    public function test_selecting_purchase_order_prefills_empty_fields(): void
    {
        $this->withAuthorizedUser();

        $category = InventoryCategory::factory()->createOne();
        $po = PurchaseOrder::factory()
            ->has(PurchaseOrderLine::factory()->state(['price' => 250, 'price_vat' => 52.5]), 'lines')
            ->create(['date' => '2026-03-15']);

        Livewire::test(CreateInventoryItem::class)
            ->fillForm([
                'purchase_order_id' => $po->id,
            ])
            ->assertFormFieldExists('date')
            ->assertFormFieldExists('amount');

        // Verify the form state was updated by the afterStateUpdated callback
        $formData = Livewire::test(CreateInventoryItem::class)
            ->fillForm([
                'purchase_order_id' => $po->id,
            ])
            ->get('data');

        static::assertSame('2026-03-15', $formData['date']);
        static::assertEquals(250, $formData['amount']);
    }
}
```

### Step 11.2 — `InventoryCategoryResourceTest`

```bash
./Taskfile artisan make:test --phpunit InventoryCategoryResourceTest
```

File: `tests/Feature/Filament/InventoryCategories/InventoryCategoryResourceTest.php`

Mirror `tests/Feature/Filament/StorageSpaceLocations/StorageSpaceLocationResourceTest.php` (including the "cannot delete category with items" case):

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\InventoryCategories;

use App\Filament\Admin\Resources\InventoryCategories\Pages\CreateInventoryCategory;
use App\Filament\Admin\Resources\InventoryCategories\Pages\EditInventoryCategory;
use App\Filament\Admin\Resources\InventoryCategories\Pages\ListInventoryCategories;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use Livewire\Livewire;
use Tests\Concerns\WithAuthorizedUser;
use Tests\FeatureTestCase;

final class InventoryCategoryResourceTest extends FeatureTestCase
{
    use WithAuthorizedUser;

    public function test_can_list_inventory_categories(): void
    {
        $this->withAuthorizedUser();

        InventoryCategory::factory()->createOne(['name' => 'Windsurf materiaal']);
        InventoryCategory::factory()->createOne(['name' => 'Boot materialen']);

        Livewire::test(ListInventoryCategories::class)
            ->assertCanSeeTableRecords(InventoryCategory::all());
    }

    public function test_can_create_inventory_category(): void
    {
        $this->withAuthorizedUser();

        Livewire::test(CreateInventoryCategory::class)
            ->fillForm([
                'name' => 'Apparaten',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('inventory_categories', [
            'name' => 'Apparaten',
        ]);
    }

    public function test_can_edit_inventory_category(): void
    {
        $this->withAuthorizedUser();

        $category = InventoryCategory::factory()->createOne(['name' => 'Apparaten']);

        Livewire::test(EditInventoryCategory::class, ['record' => $category->id])
            ->fillForm([
                'name' => 'Elektronica',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('inventory_categories', [
            'id' => $category->id,
            'name' => 'Elektronica',
        ]);
    }

    public function test_can_delete_inventory_category(): void
    {
        $this->withAuthorizedUser();

        $category = InventoryCategory::factory()->createOne(['name' => 'Apparaten']);

        Livewire::test(EditInventoryCategory::class, ['record' => $category->id])
            ->assertActionEnabled('delete')
            ->callAction('delete');

        $this->assertDatabaseMissing('inventory_categories', [
            'id' => $category->id,
        ]);
    }

    public function test_can_not_delete_inventory_category_with_items(): void
    {
        $this->withAuthorizedUser();

        $category = InventoryCategory::factory()
            ->has(InventoryItem::factory())
            ->createOne(['name' => 'Apparaten']);

        Livewire::test(EditInventoryCategory::class, ['record' => $category->id])
            ->assertActionHidden('delete');
    }
}
```

### Step 11.3 — Run tests

```bash
./Taskfile artisan test --compact --filter=InventoryItemResourceTest
./Taskfile artisan test --compact --filter=InventoryCategoryResourceTest
```

Then ask the user whether to run the full suite.

---

## Summary of all files

### New files

| File | Purpose |
|------|---------|
| `database/migrations/____create_inventory_categories_table.php` | Categories table |
| `database/migrations/____create_inventory_items_table.php` | Items table |
| `app/Models/InventoryCategory.php` | Eloquent model |
| `app/Models/InventoryItem.php` | Eloquent model |
| `app/Observers/InventoryItemObserver.php` | Receipt file cleanup on delete |
| `database/factories/InventoryCategoryFactory.php` | Factory |
| `database/factories/InventoryItemFactory.php` | Factory |
| `app/Policies/InventoryItemPolicy.php` | Policy |
| `app/Policies/InventoryCategoryPolicy.php` | Policy |
| `app/Filament/Admin/Resources/InventoryItems/InventoryItemResource.php` | Resource |
| `app/Filament/Admin/Resources/InventoryItems/Pages/{List,Create,Edit}InventoryItem*.php` | Pages |
| `app/Filament/Admin/Resources/InventoryItems/Schemas/InventoryItemForm.php` | Form schema |
| `app/Filament/Admin/Resources/InventoryItems/Tables/InventoryItemsTable.php` | Table schema |
| `app/Filament/Admin/Resources/InventoryCategories/InventoryCategoryResource.php` | Resource |
| `app/Filament/Admin/Resources/InventoryCategories/Pages/{List,Create,Edit}InventoryCategory*.php` | Pages |
| `app/Filament/Admin/Resources/InventoryCategories/Schemas/InventoryCategoryForm.php` | Form schema |
| `app/Filament/Admin/Resources/InventoryCategories/Tables/InventoryCategoriesTable.php` | Table schema |
| `database/seeders/InventoryCategorySeeder.php` | Example categories seeder |
| `tests/Feature/Filament/InventoryItems/InventoryItemResourceTest.php` | Feature test |
| `tests/Feature/Filament/InventoryCategories/InventoryCategoryResourceTest.php` | Feature test |

### Modified files

| File | Change |
|------|--------|
| `app/Domain/Authorization/ResourcePermission.php` | Add `inventory_items` + `inventory_categories` permissions |
| `database/seeders/RolePermissionSeeder.php` | Grant permissions to financial + technical roles |
| `app/Filament/Admin/Navigation/NavigationGroup.php` | Add `Inventory` case |
| `database/seeders/DatabaseSeeder.php` | Register `InventoryCategorySeeder` |
| `lang/nl/labels.php` | Add inventory labels + navigation group label |

---

## Execution order

1. **Migrations** (Phase 1) → `./Taskfile artisan migrate`
2. **Models + observer** (Phase 2)
3. **Factories** (Phase 3)
4. **Policies** (Phase 4)
5. **Permissions** (Phase 5) — `ResourcePermission` + `RolePermissionSeeder`
6. **Navigation group** (Phase 6)
7. **Filament resources** (Phases 7–8)
8. **Labels** (Phase 9)
9. **Seeders** (Phase 10)
10. **Tests** (Phase 11) — write and run
