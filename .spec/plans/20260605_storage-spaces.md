# Storage Spaces Feature Plan

## Overview

Introduce a `StorageSpace` model (representing a physical surf storage space identified by a `location` and a `number`) and a `StorageSpaceRental` model (a time-bounded assignment of a storage space to a member).

Storage spaces are managed via a dedicated Filament resource that includes a bulk-generation header action. Rentals are visible both from the storage space resource (via a relation manager) and from the member resource (via an additional relation manager tab). A custom validation rule prevents the same storage space from being assigned to the same member in overlapping date ranges.

> **Naming convention note**: The user prompt refers to "start and stop date", but the rest of the codebase consistently uses `start_date` / `end_date` (see `member_objects`, `activities`, `billable_item_instances`). We follow the existing convention and use `end_date` for the rental end column.

> **No billing integration**: This feature is purely administrative. No `Observer` is needed (compare with `MemberObjectObserver` and `ActivityMemberObserver` which trigger billing on create/delete). The user's requirements explicitly limit the scope to assignment tracking.

---

## Database

### Migration 1: `create_storage_spaces_table`

File: `database/migrations/YYYY_MM_DD_HHMMSS_create_storage_spaces_table.php`

Table: `storage_spaces`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `location` | string | e.g. `Berging A`, `Schuur Noord` |
| `number` | unsignedInteger | the space number within that location |
| `timestamps` | | |

Add a unique composite constraint: `unique(['location', 'number'])` — no two spaces may share the same `(location, number)` combination.

```php
return new class() extends Migration {
    public function up(): void
    {
        Schema::create('storage_spaces', static function (Blueprint $table): void {
            $table->id();
            $table->string('location');
            $table->unsignedInteger('number');
            $table->timestamps();

            $table->unique(['location', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storage_spaces');
    }
};
```

### Migration 2: `create_storage_space_rentals_table`

File: `database/migrations/YYYY_MM_DD_HHMMSS_create_storage_space_rentals_table.php`

Table: `storage_space_rentals`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `storage_space_id` | foreignId | `constrained('storage_spaces')->cascadeOnDelete()` |
| `member_id` | foreignId | `constrained('members')->cascadeOnDelete()` |
| `start_date` | date | required |
| `end_date` | date nullable | null means open-ended (ongoing) |
| `timestamps` | | |

```php
return new class() extends Migration {
    public function up(): void
    {
        Schema::create('storage_space_rentals', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('storage_space_id')->constrained('storage_spaces')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storage_space_rentals');
    }
};
```

> **No database-level uniqueness constraint** is added here. The overlap rule (same member + same space, overlapping dates) is enforced at the PHP level via a custom validation rule applied in the Filament form. This is consistent with the project's approach — none of the date-bounded pivot-style models (`member_objects`, `activities`, `activity_member`) use DB-level overlap constraints, and PostgreSQL `EXCLUDE USING gist` would need the `btree_gist` extension installed.

---

## Models

### `App\Models\StorageSpace`

File: `app/Models/StorageSpace.php`

Mirrors the `Member` model style: `#[Guarded]` attribute (not `#[Fillable]`), `HasFactory` trait, explicit PHPDoc generics on relation return types.

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Guarded('id', 'updated_at', 'created_at')]
final class StorageSpace extends Model
{
    use HasFactory;

    /** @return HasMany<StorageSpaceRental, $this> */
    public function rentals(): HasMany
    {
        return $this->hasMany(StorageSpaceRental::class);
    }
}
```

### `App\Models\StorageSpaceRental`

File: `app/Models/StorageSpaceRental.php`

Mirrors the `MemberObject` model style: `#[Fillable]` attribute, `HasFactory` trait, both date columns cast to `date`, PHPDoc generics on relations.

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['storage_space_id', 'member_id', 'start_date', 'end_date'])]
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

### Update `App\Models\Member`

File: `app/Models/Member.php`

Add a `HasMany` relation to the existing list of relations (around line 56–60, alongside `memberObjects`):

```php
/** @return HasMany<StorageSpaceRental, $this> */
public function storageSpaceRentals(): HasMany
{
    return $this->hasMany(StorageSpaceRental::class);
}
```

---

## Factories

### `database/factories/StorageSpaceFactory.php`

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\StorageSpace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StorageSpace>
 */
final class StorageSpaceFactory extends Factory
{
    protected $model = StorageSpace::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'location' => $this->faker->randomElement([
                'Container 3',
                'Container 4',
                'Container 5',
            ]),
            'number' => $this->faker->unique()->numberBetween(1, 30),
        ];
    }
}
```

### `database/factories/StorageSpaceRentalFactory.php`

Uses `for()` to explicitly associate the parent IDs (matches the `MemberObjectFactory` pattern of using `Member::factory()` / `MemberObjectType::factory()` directly in the definition — both styles exist in the codebase; using the factory reference inline is the more common one).

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Member;
use App\Models\StorageSpace;
use App\Models\StorageSpaceRental;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StorageSpaceRental>
 */
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

## Validation Rule

### `app/Rules/NoOverlappingStorageSpaceRental.php`

New directory: `app/Rules/` (does not exist yet; this is the only `ValidationRule` in the project — the directory is created for this feature).

The rule checks whether a new or edited rental period overlaps with any existing rental for the **same storage space + same member** pair.

Two date ranges `[A, B]` and `[C, D]` overlap when `A <= D AND B >= C` (where `null` end dates are treated as open-ended / `+∞`).

```php
<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\StorageSpaceRental;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;

final readonly class NoOverlappingStorageSpaceRental implements ValidationRule
{
    /**
     * @param int[] $excludeRentalIds
     */
    public function __construct(
        private int $storageSpaceId,
        private int $memberId,
        private ?string $startDate,
        private ?string $endDate,
        private array $excludeRentalIds = [],
    ) {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Treat null end as far-future to short-circuit the comparison.
        $effectiveEndDate = $this->endDate ?? '9999-12-31';
        $effectiveStartDate = $this->startDate ?? '0001-01-01';

        $query = StorageSpaceRental::query()
            ->where('storage_space_id', $this->storageSpaceId)
            ->where('member_id', $this->memberId)
            // Existing rental starts on/before new rental ends.
            ->where('start_date', '<=', $effectiveEndDate)
            // Existing rental ends on/after new rental starts (or is open-ended).
            ->where(static function (Builder $q) use ($effectiveStartDate): void {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', $effectiveStartDate);
            });

        if ($this->excludeRentalIds !== null) {
            $query->whereNotIn('id', '!=', $this->excludeRentalId);
        }

        if ($query->exists()) {
            $fail(__('validation.no_overlapping_storage_space_rental'));
        }
    }
}
```

> **Why a custom rule (not a unique index)**: As noted in the migration section, the codebase does not rely on DB-level overlap exclusion constraints. The rule is applied in the Filament form via `->rules()` and uses `Get` to reactively pick up the current form state.

### Validation message

Add to `lang/nl/validation.php` (next to the other custom rule translations in the file):

```php
'no_overlapping_storage_space_rental' => 'Deze opslagruimte is voor dit lid al toegewezen in een overlappende periode.',
```

> The `validation.php` file already contains a fully-translated Dutch message catalog. Custom rule messages are added at the top level (alongside `accepted`, `active_url`, etc.) — not inside a `custom` sub-array.

---

## Filament Resource Structure

```
app/Filament/Admin/Resources/StorageSpaces/
  StorageSpaceResource.php
  Pages/
    ListStorageSpaces.php
    CreateStorageSpace.php
    EditStorageSpace.php
  Actions/
    GenerateStorageSpacesAction.php
  RelationManagers/
    StorageSpaceRentalsRelationManager.php
  Schemas/
    StorageSpaceForm.php
    StorageSpaceRentalForm.php
  Tables/
    StorageSpacesTable.php
```

### `StorageSpaceResource.php`

Mirrors `HouseholdResource` (uses `Heroicon::ArchiveBox` for the navigation icon, also used by `MemberObjectTypeResource` for "object" semantics — pick a different icon here to avoid confusion). `Heroicon::Squares2x2` is a good fit for a grid of storage spaces.

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\StorageSpaces;

use App\Filament\Admin\Navigation\NavigationGroup;
use App\Filament\Admin\Resources\StorageSpaces\Pages\CreateStorageSpace;
use App\Filament\Admin\Resources\StorageSpaces\Pages\EditStorageSpace;
use App\Filament\Admin\Resources\StorageSpaces\Pages\ListStorageSpaces;
use App\Filament\Admin\Resources\StorageSpaces\RelationManagers\StorageSpaceRentalsRelationManager;
use App\Filament\Admin\Resources\StorageSpaces\Schemas\StorageSpaceForm;
use App\Filament\Admin\Resources\StorageSpaces\Tables\StorageSpacesTable;
use App\Models\StorageSpace;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

final class StorageSpaceResource extends Resource
{
    protected static bool $isGloballySearchable = false;

    protected static ?string $model = StorageSpace::class;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::MemberAdministration;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Squares2x2;

    public static function form(Schema $schema): Schema
    {
        return StorageSpaceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StorageSpacesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            StorageSpaceRentalsRelationManager::make(),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStorageSpaces::route('/'),
            'create' => CreateStorageSpace::route('/create'),
            'edit' => EditStorageSpace::route('/{record}/edit'),
        ];
    }

    public static function getLabel(): string
    {
        return __('labels.storage_space');
    }

    public static function getPluralLabel(): string
    {
        return __('labels.storage_spaces');
    }
}
```

### `Pages/ListStorageSpaces.php`

Wires the bulk-generation action alongside the standard `CreateAction` (same pattern as `ListHouseholds`):

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\StorageSpaces\Pages;

use App\Filament\Admin\Resources\StorageSpaces\Actions\GenerateStorageSpacesAction;
use App\Filament\Admin\Resources\StorageSpaces\StorageSpaceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListStorageSpaces extends ListRecords
{
    protected static string $resource = StorageSpaceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            GenerateStorageSpacesAction::make(),
        ];
    }
}
```

### `Pages/CreateStorageSpace.php`

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\StorageSpaces\Pages;

use App\Filament\Admin\Resources\StorageSpaces\StorageSpaceResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateStorageSpace extends CreateRecord
{
    protected static string $resource = StorageSpaceResource::class;
}
```

### `Pages/EditStorageSpace.php`

Combines the relation manager tabs with the form content (same pattern as `EditActivity`):

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\StorageSpaces\Pages;

use App\Filament\Admin\Resources\StorageSpaces\StorageSpaceResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

final class EditStorageSpace extends EditRecord
{
    protected static string $resource = StorageSpaceResource::class;

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function getContentTabLabel(): string
    {
        return __('labels.storage_space');
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
```

### `Actions/GenerateStorageSpacesAction.php`

Header action on the list page. Asks for a location and a from/to number range, then bulk-creates spaces. Uses `firstOrCreate` for idempotency so re-running the action for the same range is safe (matches the pattern of "tolerant" bulk operations seen elsewhere in the codebase).

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\StorageSpaces\Actions;

use App\Models\StorageSpace;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;

final class GenerateStorageSpacesAction
{
    public static function make(): Action
    {
        return Action::make('generate_storage_spaces')
            ->label(__('labels.generate_storage_spaces'))
            ->icon(Heroicon::SquaresPlus)
            ->schema([
                TextInput::make('location')
                    ->label(__('labels.location'))
                    ->required(),
                TextInput::make('from_number')
                    ->label(__('labels.from_number'))
                    ->integer()
                    ->minValue(1)
                    ->required(),
                TextInput::make('to_number')
                    ->label(__('labels.to_number'))
                    ->integer()
                    ->minValue(1)
                    ->required()
                    ->gte('from_number'),
            ])
            ->action(static function (array $data): void {
                $location = $data['location'];
                $fromNumber = (int) $data['from_number'];
                $toNumber = (int) $data['to_number'];

                for ($number = $fromNumber; $number <= $toNumber; $number++) {
                    StorageSpace::firstOrCreate([
                        'location' => $location,
                        'number' => $number,
                    ]);
                }
            })
            ->successNotificationTitle(__('notifications.storage_spaces_generated'));
    }
}
```

> `->gte('from_number')` is Filament v5's built-in field comparison rule. Verify availability with `php artisan` completion or `search-docs` before relying on it; fall back to a custom rule if it does not exist in v5.

### `Schemas/StorageSpaceForm.php`

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\StorageSpaces\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

final class StorageSpaceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('location')
                ->label(__('labels.location'))
                ->required()
                ->maxLength(255),
            TextInput::make('number')
                ->label(__('labels.space_number'))
                ->integer()
                ->minValue(1)
                ->required(),
        ]);
    }
}
```

### `Schemas/StorageSpaceRentalForm.php`

Provides two static factory methods for the two relation-manager contexts (storage-space side vs. member side). Both forms share the date picker block. The implicit foreign key is added as a `Hidden` field — this guarantees the FK is present in form state regardless of Filament's relation-manager FK-injection behaviour and makes the closure rules robust.

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\StorageSpaces\Schemas;

use App\Models\Member;
use App\Models\StorageSpace;
use App\Models\StorageSpaceRental;
use App\Rules\NoOverlappingStorageSpaceRental;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

final class StorageSpaceRentalForm
{
    /**
     * Form used from the relation manager on the StorageSpaceResource.
     * The storage_space_id is implicit (owner record) and added as a Hidden
     * field so the overlap rule has access to it via $get().
     */
    public static function forStorageSpace(Schema $schema): Schema
    {
        return $schema->components([
            Hidden::make('storage_space_id'),
            Select::make('member_id')
                ->label(__('labels.member'))
                ->relationship('member', 'id')
                ->getOptionLabelFromRecordUsing(static fn (Member $record) => $record->name)
                ->searchable(['last_name', 'first_name', 'infix_name'])
                ->required()
                ->live(),
            ...self::datePickers(),
        ]);
    }

    /**
     * Form used from the relation manager on the MemberResource.
     * The member_id is implicit (owner record) and added as a Hidden
     * field so the overlap rule has access to it via $get().
     */
    public static function forMember(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('storage_space_id')
                ->label(__('labels.storage_space'))
                ->relationship('storageSpace', 'id')
                ->getOptionLabelFromRecordUsing(
                    static fn (StorageSpace $record) => "{$record->location} #{$record->number}"
                )
                ->searchable(['location', 'number'])
                ->required()
                ->live(),
            Hidden::make('member_id'),
            ...self::datePickers(),
        ]);
    }

    /**
     * @return array<\Filament\Forms\Components\Component>
     */
    private static function datePickers(): array
    {
        return [
            DatePicker::make('start_date')
                ->label(__('labels.start_date'))
                ->native(false)
                ->default(now())
                ->required()
                ->live()
                ->rules(static function (Get $get, ?Model $record): array {
                    $storageSpaceId = $get('storage_space_id');
                    $memberId = $get('member_id');

                    if ($storageSpaceId === null || $memberId === null) {
                        return [];
                    }

                    return [
                        new NoOverlappingStorageSpaceRental(
                            storageSpaceId: (int) $storageSpaceId,
                            memberId: (int) $memberId,
                            startDate: $get('start_date'),
                            endDate: $get('end_date'),
                            excludeRentalId: $record?->id,
                        ),
                    ];
                }),
            DatePicker::make('end_date')
                ->label(__('labels.end_date'))
                ->native(false)
                ->afterOrEqual('start_date'),
        ];
    }
}
```

> **Why `Hidden` for the implicit FK**: Filament does inject the parent FK into the form state of relation managers, but the behaviour can be inconsistent across versions and edit/create contexts. Adding a `Hidden` field makes the rule's `$get('storage_space_id')` / `$get('member_id')` calls reliable and matches the defensive pattern used elsewhere in the project (e.g. `HouseholdMemberActions` ensures fields are explicitly set).

> **Pre-populating the Hidden field**: The relation manager will fill the hidden field with the owner record's ID automatically when creating — verify in development and, if needed, override `mount()` or use a `mutateFormDataBeforeCreate()` hook in the relation manager.

### `Tables/StorageSpacesTable.php`

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\StorageSpaces\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class StorageSpacesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('location')
                    ->label(__('labels.location'))
                    ->sortable()
                    ->searchable(),
                TextColumn::make('number')
                    ->label(__('labels.space_number'))
                    ->sortable()
                    ->numeric(),
            ])
            ->defaultSort('location')
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

### `RelationManagers/StorageSpaceRentalsRelationManager.php` (StorageSpace side)

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\StorageSpaces\RelationManagers;

use App\Filament\Admin\Resources\StorageSpaces\Schemas\StorageSpaceRentalForm;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

final class StorageSpaceRentalsRelationManager extends RelationManager
{
    protected static string $relationship = 'rentals';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('member.name')->label(__('labels.member')),
                TextColumn::make('start_date')->label(__('labels.start_date'))->date(),
                TextColumn::make('end_date')->label(__('labels.end_date'))->date(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->schema(StorageSpaceRentalForm::forStorageSpace(...)),
            ])
            ->recordActions([
                EditAction::make()
                    ->schema(StorageSpaceRentalForm::forStorageSpace(...)),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('labels.storage_space_rentals');
    }

    public static function getModelLabel(): string
    {
        return mb_strtolower(__('labels.storage_space_rental'));
    }

    public static function getPluralModelLabel(): string
    {
        return mb_strtolower(__('labels.storage_space_rentals'));
    }
}
```

---

## Member Resource — Storage Space Rentals Relation Manager

### New file: `app/Filament/Admin/Resources/Members/RelationManagers/StorageSpaceRentalsRelationManager.php`

Shows the rentals from the member perspective. The `$relationship` must correspond to `Member::storageSpaceRentals()`.

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Members\RelationManagers;

use App\Filament\Admin\Resources\StorageSpaces\Schemas\StorageSpaceRentalForm;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

final class StorageSpaceRentalsRelationManager extends RelationManager
{
    protected static string $relationship = 'storageSpaceRentals';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('storageSpace.location')->label(__('labels.location')),
                TextColumn::make('storageSpace.number')->label(__('labels.space_number')),
                TextColumn::make('start_date')->label(__('labels.start_date'))->date(),
                TextColumn::make('end_date')->label(__('labels.end_date'))->date(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->schema(StorageSpaceRentalForm::forMember(...)),
            ])
            ->recordActions([
                EditAction::make()
                    ->schema(StorageSpaceRentalForm::forMember(...)),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('labels.storage_spaces');
    }

    public static function getModelLabel(): string
    {
        return mb_strtolower(__('labels.storage_space_rental'));
    }

    public static function getPluralModelLabel(): string
    {
        return mb_strtolower(__('labels.storage_space_rentals'));
    }
}
```

### Update `MemberResource.php`

File: `app/Filament/Admin/Resources/Members/MemberResource.php`

Add the import and register the relation manager in `getRelations()` (around line 46–55):

```php
use App\Filament\Admin\Resources\Members\RelationManagers\StorageSpaceRentalsRelationManager;

public static function getRelations(): array
{
    return [
        HouseholdMembersRelationManager::make(),
        InvoicesRelationManager::make(),
        BillableItemInstancesRelationManager::make(),
        ActivitiesRelationManager::make(),
        MemberObjectsRelationManager::make(),
        StorageSpaceRentalsRelationManager::make(),
    ];
}
```

---

## Language Labels

### Update `lang/nl/labels.php`

Add the following keys (preserve alphabetical-ish order — the file is not strictly alphabetical but is grouped by topic; place these after the existing `member_object_types` block):

```php
'storage_space' => 'Opslagruimte',
'storage_spaces' => 'Opslagruimten',
'storage_space_rental' => 'Verhuur',
'storage_space_rentals' => 'Verhuren',
'location' => 'Locatie',
'space_number' => 'Nummer',
'from_number' => 'Van nummer',
'to_number' => 'Tot nummer',
'generate_storage_spaces' => 'Opslagruimten genereren',
'rentals_count' => 'Aantal verhuren',
```

> **`location` is not yet used in `labels.php`**, so the key is safe to add. `space_number` is also new. `start_date` and `end_date` already exist and are reused.

### Update `lang/nl/validation.php`

Add at the top level (next to the other custom rule keys — see the current structure of the file):

```php
'no_overlapping_storage_space_rental' => 'Deze opslagruimte is voor dit lid al toegewezen in een overlappende periode.',
```

### Update `lang/nl/notifications.php`

Add:

```php
'storage_spaces_generated' => 'Opslagruimten succesvol aangemaakt.',
```

---

## Tests

Follow project convention: PHPUnit feature tests, extending `FeatureTestCase` (uses `LazilyRefreshDatabase`). Tests live under `tests/Feature/...`. Create a `StorageSpaces/` subfolder for tests of this feature.

### `tests/Feature/StorageSpaces/NoOverlappingStorageSpaceRentalTest.php`

Exhaustive tests for the validation rule. The rule is a pure PHP class — instantiate it directly with constructor args and call `validate('start_date', $value, $fail)`. Use a `Mockery::mock(Closure::class)` to capture the failure message; an even simpler approach is to wrap `$fail` in a closure that captures the message into a local variable.

- **Happy path – no overlap**: a rental exists Jan 1 – Jun 30, a new rental Jul 1 – Dec 31 for the same space+member passes
- **Overlap – fully contained**: existing Jan 1 – Dec 31, new Mar 1 – May 31 → fails
- **Overlap – start boundary**: existing Jun 1 – Dec 31, new Jan 1 – May 31 → fails (new end is after existing start, and new start is before existing start… wait, this is NOT an overlap — verify with diagram)
- **Overlap – end boundary**: existing Jan 1 – Jun 30, new Jul 1 – Dec 31 → does NOT overlap (new start = existing end)
- **Open-ended existing**: existing Jan 1 – null, new Mar 1 – Jun 30 → fails
- **Open-ended new**: existing Jan 1 – Jun 30, new Mar 1 – null → fails
- **Both open-ended**: existing Jan 1 – null, new Feb 1 – null → fails
- **Exclude current record on edit**: existing rental can be edited without triggering overlap on itself (uses `excludeRentalId`)
- **Different member, same space, overlapping dates** → passes
- **Same member, different space, overlapping dates** → passes
- **Strict end boundary (touching is not overlap)**: existing Jan 1 – Jun 30, new Jun 30 – Dec 31 → does NOT overlap (existing.end_date >= new.start_date is satisfied but existing.start_date <= new.end_date is also satisfied — this is an edge case; pick a convention and document it). Recommendation: treat the end of the existing range as the day the space is freed up (i.e. overlap if `existing.start_date < new.end_date AND (existing.end_date IS NULL OR existing.end_date > new.start_date)`). Adjust the rule accordingly to use strict `<` / `>` instead of `<=` / `>=`.

> **Boundary convention decision**: After picking the convention, adjust the rule's `where` clauses to match. The default convention in the rule above is inclusive end dates on both sides — this means a rental from Jan 1 – Jun 30 and a new rental from Jun 30 – Dec 31 would be flagged as overlapping. If the customer wants the end date to be inclusive (i.e. the space is freed at the end of the day on Jun 30), then the rule should use `existing.start_date < new.end_date` (strict less-than) — pick one and be consistent.

### `tests/Feature/StorageSpaces/GenerateStorageSpacesActionTest.php`

Tests for the bulk-creation action. The action is a static factory `GenerateStorageSpacesAction::make()` returning a Filament `Action`. To unit-test the side-effect of `action(...)`, instantiate the action, call its `action()` closure with a synthetic `$data` array, and assert on the database. This bypasses the Filament UI flow entirely.

- Creates `StorageSpace` records for the given location and number range
- Skips already-existing `(location, number)` combinations (idempotent — uses `firstOrCreate`)
- Does not create spaces outside the given range
- Validates that `to_number >= from_number` is required (test by setting `to_number < from_number` and asserting the action's form rejects the input — or rely on the form's `->gte('from_number')` rule and only test the happy path of the closure)
- Creates spaces for an already-partially-existing range (e.g. range 1–10, but 3, 5, 7 already exist → creates 1, 2, 4, 6, 8, 9, 10)

### `tests/Feature/StorageSpaces/StorageSpaceRentalModelTest.php`

Model/relation sanity tests:
- A rental can be created for a `(storage_space, member)` pair
- `StorageSpace::rentals()` returns the rentals for that space
- `StorageSpaceRental::storageSpace()` returns the parent space
- `StorageSpaceRental::member()` returns the parent member
- `Member::storageSpaceRentals()` returns the rentals for that member
- `start_date` and `end_date` are cast to `Carbon` instances

### `tests/Feature/Filament/StorageSpaces/StorageSpaceResourceTest.php`

Standard Filament resource tests (mirror the style of `tests/Feature/Filament/...` if any exist; otherwise follow the patterns in the existing `Activities` resource):
- Can list storage spaces
- Can create a storage space
- Can edit a storage space
- Can delete a storage space
- The bulk generation action creates the expected records (using `Livewire::test(ListStorageSpaces::class)->callAction(...)` or equivalent)

> **Existing pattern check**: Look for any existing `tests/Feature/Filament/` test files to follow the convention. If none exist, write integration tests using `Livewire::test()` to interact with the resource pages.

---

## Implementation Order

1. Create migrations (`storage_spaces`, `storage_space_rentals`) — generate with `php artisan make:migration --no-interaction`
2. Create `StorageSpace` model + factory
3. Create `StorageSpaceRental` model + factory
4. Add `storageSpaceRentals()` relation to `Member` model
5. Create `app/Rules/NoOverlappingStorageSpaceRental` rule + add validation message to `lang/nl/validation.php`
6. Add language labels to `lang/nl/labels.php` and `lang/nl/notifications.php`
7. Create `StorageSpaceResource` (Filament): resource class, form schema, table, pages
8. Create `GenerateStorageSpacesAction` and wire into `ListStorageSpaces`
9. Create `StorageSpaceRentalForm` and `StorageSpaceRentalsRelationManager` (storage-space side) and register in `StorageSpaceResource`
10. Create `StorageSpaceRentalsRelationManager` (member side) and register in `MemberResource`
11. Run migrations: `php artisan migrate`
12. Write and run tests: `php artisan test --compact tests/Feature/StorageSpaces`

---

## Key Design Decisions

| Decision | Rationale |
|---|---|
| `StorageSpaceRental` is a standalone `Model` (not a Pivot) | Needs its own `id`, timestamps, and factory; mirrors `MemberObject` pattern |
| Use `end_date` (not `stop_date`) | Consistent with the rest of the codebase (`member_objects`, `activities`, `billable_item_instances`) |
| Overlap validated at PHP level (not DB exclusion constraint) | Consistent with rest of app; PostgreSQL `EXCLUDE USING gist` would need `btree_gist` extension install |
| Two relation manager classes (one per side) | Different columns + different form context; avoids coupling |
| `StorageSpaceRentalForm` has two static factory methods | One set of date pickers is shared; which FK select to show depends on context |
| Implicit FK exposed via `Hidden` field | Robust against Filament FK-injection behaviour variations across v5 sub-versions |
| `firstOrCreate` in bulk action | Idempotent: re-running the action for the same range is safe |
| `to_number >= from_number` via `->gte('from_number')` | Filament v5 built-in field comparison rule (verify availability in dev) |
| No `Observer` on `StorageSpaceRental` | The feature is purely administrative — no billing integration requested |
| No seeder | No production data is needed; the bulk-generation action fills the table on demand |
| `Heroicon::Squares2x2` for resource, `Heroicon::SquaresPlus` for action | Visually distinguishes the resource from the action and matches "grid of boxes" semantics |

---

## Files Created / Modified

### Created

- `database/migrations/YYYY_MM_DD_HHMMSS_create_storage_spaces_table.php`
- `database/migrations/YYYY_MM_DD_HHMMSS_create_storage_space_rentals_table.php`
- `app/Models/StorageSpace.php`
- `app/Models/StorageSpaceRental.php`
- `app/Rules/NoOverlappingStorageSpaceRental.php` (new directory)
- `database/factories/StorageSpaceFactory.php`
- `database/factories/StorageSpaceRentalFactory.php`
- `app/Filament/Admin/Resources/StorageSpaces/StorageSpaceResource.php`
- `app/Filament/Admin/Resources/StorageSpaces/Pages/ListStorageSpaces.php`
- `app/Filament/Admin/Resources/StorageSpaces/Pages/CreateStorageSpace.php`
- `app/Filament/Admin/Resources/StorageSpaces/Pages/EditStorageSpace.php`
- `app/Filament/Admin/Resources/StorageSpaces/Actions/GenerateStorageSpacesAction.php`
- `app/Filament/Admin/Resources/StorageSpaces/RelationManagers/StorageSpaceRentalsRelationManager.php`
- `app/Filament/Admin/Resources/StorageSpaces/Schemas/StorageSpaceForm.php`
- `app/Filament/Admin/Resources/StorageSpaces/Schemas/StorageSpaceRentalForm.php`
- `app/Filament/Admin/Resources/StorageSpaces/Tables/StorageSpacesTable.php`
- `app/Filament/Admin/Resources/Members/RelationManagers/StorageSpaceRentalsRelationManager.php`
- `tests/Feature/StorageSpaces/NoOverlappingStorageSpaceRentalTest.php`
- `tests/Feature/StorageSpaces/GenerateStorageSpacesActionTest.php`
- `tests/Feature/StorageSpaces/StorageSpaceRentalModelTest.php`
- `tests/Feature/Filament/StorageSpaces/StorageSpaceResourceTest.php`

### Modified

- `app/Models/Member.php` (add `storageSpaceRentals()` relation)
- `app/Filament/Admin/Resources/Members/MemberResource.php` (register new relation manager)
- `lang/nl/labels.php` (add storage space labels)
- `lang/nl/validation.php` (add overlap rule message)
- `lang/nl/notifications.php` (add generated notification)
