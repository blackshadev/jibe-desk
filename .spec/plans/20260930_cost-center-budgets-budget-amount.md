# Implementation Plan: Cost Center Budget Amount, Budget Management & Results

**Date**: Wed Sep 30 2026

## Overview

Extend the existing bookkeeping solution so that, per fiscal year, a cost center carries both a **starting amount** (already present) and a **budget amount** (new). A financial administrator manages these through a Filament relation manager on the cost center edit page. The `CostCenterResults` page gains a budget column, highlights the actual total red when it exceeds the budget, and shows a closing balance, a budget-vs-amount difference, and whole-year totals.

## Current situation (researched)

- `cost_center_budgets` table already exists (`database/migrations/2026_06_23_134318_create_cost_center_budgets_table.php`) with `year`, `cost_center_id`, `starting_amount`, and a `unique(['cost_center_id','year'])` constraint.
- `App\Models\CostCenterBudget` (`app/Models/CostCenterBudget.php`) is `#[Fillable(['year','cost_center_id','starting_amount'])]` — no `budget_amount`, no casts.
- `App\Models\CostCenter::budgets()` HasMany relation already exists.
- A form schema `app/Filament/Admin/Resources/CostCenters/Schemas/CostCenterBudgetForm.php` already exists (year + starting_amount) but is **not referenced by anything** — there is no relation manager yet.
- `app/Filament/Admin/Resources/CostCenters/Pages/EditCostCenter.php` has no relation managers.
- `app/Filament/Admin/Pages/CostCenterResults.php` renders a custom table (raw joins + `DB::raw` aliases) with columns `number`, `title`, `starting_amount`, `total_amount`, and `result` where `result = starting_amount + total_amount`. It lives in `BookkeepingCluster`, which is gated to `RoleName::FinancialAdministration` (`app/Filament/Admin/Clusters/Bookkeeping/BookkeepingCluster.php`).
- `BookkeepingRecordDbRepository::getResultsForYear()` and the `CostCenterYearResult` DTO exist but are **not used by the page** (the page uses its own inline query). They are left unchanged by this plan.
- Permissions for `cost_center_budgets` already exist: `ResourcePermission` cases, `CostCenterBudgetPolicy`, and `RolePermissionSeeder::seedFinancialAdministration()` already grants `allPermissionsFor('cost_center_budgets')`. **No authorization changes required.**
- Filament v5.6.3. Relation-manager CRUD convention in this codebase: `table()` with `->headerActions([CreateAction::make()->schema(FormClass::configure(...))])` and `->recordActions([EditAction::make()->schema(...), DeleteAction::make()])` (see `app/Filament/Admin/Resources/Members/RelationManagers/MemberObjectsRelationManager.php`). Custom summarizers follow `app/Filament/Admin/Resources/BankTransactions/Tables/RunningTotalSummery.php`.

## Planned changes (summary)

1. Add a `budget_amount` decimal column to `cost_center_budgets`.
2. Extend the `CostCenterBudget` model (fillable + casts), factory, and form schema with `budget_amount`.
3. Add a `CostCenterBudgetsRelationManager` (create/edit/delete) and register it on `CostCenterResource`.
4. Rework `CostCenterResults`: add `budget_amount` column, rename `result` → `closing_balance`, add a `budget_difference` column, colour `total_amount` red when it exceeds budget, and add whole-year totals (summarizers) for budget, amount and closing balance.
5. Add Dutch labels.
6. Add/adjust tests.

---

## Change 1 — Migration: add `budget_amount`

Create a forward, reversible migration (do not edit the already-run migration).

```bash
./Taskfile artisan make:migration add_budget_amount_to_cost_center_budgets_table --no-interaction
```

File: `database/migrations/YYYY_MM_DD_HHMMSS_add_budget_amount_to_cost_center_budgets_table.php`

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cost_center_budgets', static function (Blueprint $table): void {
            $table->decimal('budget_amount', 10, 3)->default(0)->after('starting_amount');
        });
    }

    public function down(): void
    {
        Schema::table('cost_center_budgets', static function (Blueprint $table): void {
            $table->dropColumn('budget_amount');
        });
    }
};
```

> `decimal(10,3)` matches the project monetary precision already used for `starting_amount`.

Run: `./Taskfile artisan migrate`.

---

## Change 2 — `CostCenterBudget` model

File: `app/Models/CostCenterBudget.php`

Add `budget_amount` to fillable and add decimal/integer casts (mirrors the sibling `App\Models\BankAccountYearBalance`).

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

#[Fillable(['year', 'cost_center_id', 'starting_amount', 'budget_amount'])]
final class CostCenterBudget extends Model
{
    use HasFactory;

    /** @return BelongsTo<CostCenter, $this> */
    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'starting_amount' => 'decimal:3',
            'budget_amount' => 'decimal:3',
        ];
    }
}
```

---

## Change 3 — `CostCenterBudgetFactory`

File: `database/factories/CostCenterBudgetFactory.php`

Add `budget_amount` to the definition.

```php
#[Override]
public function definition(): array
{
    return [
        'year' => now()->year,
        'cost_center_id' => CostCenter::factory(),
        'starting_amount' => fake()->randomFloat(2, 0, 10_000),
        'budget_amount' => fake()->randomFloat(2, 0, 10_000),
    ];
}
```

---

## Change 4 — Form schema: add `budget_amount`

File: `app/Filament/Admin/Resources/CostCenters/Schemas/CostCenterBudgetForm.php`

Add a `budget_amount` numeric input beside `starting_amount`, and a scoped unique rule on `year` (one budget per year per cost center, matching the `unique(['cost_center_id','year'])` DB constraint).

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\CostCenters\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

final class CostCenterBudgetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('year')
                            ->label(__('labels.book_year'))
                            ->required()
                            ->numeric()
                            ->minValue(2000)
                            ->maxValue(2100)
                            ->unique(
                                modifyRuleUsing: static fn (Unique $rule, RelationManager $livewire): Unique => $rule->where(
                                    'cost_center_id',
                                    $livewire->getOwnerRecord()->getKey(),
                                ),
                                ignoreRecord: true,
                            ),
                        TextInput::make('starting_amount')
                            ->label(__('labels.starting_amount'))
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01),
                        TextInput::make('budget_amount')
                            ->label(__('labels.budget'))
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01),
                    ]),
            ]);
    }
}
```

> `modifyRuleUsing` scopes the `Unique` rule to the current cost center (`cost_center_id`), and `ignoreRecord: true` keeps the existing row valid while editing. The owner cost center is read via `RelationManager::getOwnerRecord()`, the same injection pattern used by `StorageSpaceRentalForm`.

---

## Change 5 — Relation manager (new)

File: `app/Filament/Admin/Resources/CostCenters/RelationManagers/CostCenterBudgetsRelationManager.php`

Follows the `MemberObjectsRelationManager` / `StorageSpaceRentalsRelationManager` CRUD pattern.

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\CostCenters\RelationManagers;

use App\Filament\Admin\Resources\CostCenters\Schemas\CostCenterBudgetForm;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Override;

final class CostCenterBudgetsRelationManager extends RelationManager
{
    #[Override]
    protected static string $relationship = 'budgets';

    #[Override]
    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('year')
                    ->label(__('labels.book_year'))
                    ->sortable(),
                TextColumn::make('starting_amount')
                    ->label(__('labels.starting_amount'))
                    ->money('EUR')
                    ->alignEnd(),
                TextColumn::make('budget_amount')
                    ->label(__('labels.budget'))
                    ->money('EUR')
                    ->alignEnd(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->schema(CostCenterBudgetForm::configure(...)),
            ])
            ->recordActions([
                EditAction::make()
                    ->schema(CostCenterBudgetForm::configure(...)),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    #[Override]
    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('labels.budgets');
    }

    #[Override]
    public static function getModelLabel(): string
    {
        return mb_strtolower(__('labels.budget'));
    }

    #[Override]
    public static function getPluralModelLabel(): string
    {
        return mb_strtolower(__('labels.budgets'));
    }
}
```

---

## Change 6 — Register the relation manager on `CostCenterResource`

File: `app/Filament/Admin/Resources/CostCenters/CostCenterResource.php`

Add the import and a `getRelations()` method (same convention as `MemberResource` / `StorageSpaceResource`). The relation manager will appear on the edit page.

```php
use App\Filament\Admin\Resources\CostCenters\RelationManagers\CostCenterBudgetsRelationManager;
```

```php
#[Override]
public static function getRelations(): array
{
    return [
        CostCenterBudgetsRelationManager::make(),
    ];
}
```

---

## Change 7 — `CostCenterResults` page

File: `app/Filament/Admin/Pages/CostCenterResults.php`

Add import:

```php
use Filament\Tables\Columns\Summarizers\Summarizer;
```

Replace the `table()` method with a version that shows `budget_amount`, a renamed `closing_balance`, a `budget_difference` column, the red-over-budget colouring on `total_amount`, and whole-year totals.

```php
public function table(Table $table): Table
{
    return $table
        ->query($this->getTableQuery(...))
        ->columns([
            TextColumn::make('number')
                ->label(__('labels.number'))
                ->sortable(),
            TextColumn::make('title')
                ->label(__('labels.title')),
            TextColumn::make('starting_amount')
                ->label(__('labels.starting_amount'))
                ->money('EUR')
                ->alignEnd()
                ->sortable(),
            TextColumn::make('budget_amount')
                ->label(__('labels.budget'))
                ->money('EUR')
                ->alignEnd()
                ->sortable()
                ->summarize([
                    Summarizer::make('total_budget')
                        ->label(__('labels.total'))
                        ->using(fn (): float => $this->sumBudgetAmount())
                        ->numeric()
                        ->money('EUR'),
                ]),
            TextColumn::make('total_amount')
                ->label(__('labels.total'))
                ->money('EUR')
                ->alignEnd()
                ->sortable()
                ->color(static fn (float $state, CostCenter $record): ?string => $state > (float) $record->budget_amount ? 'danger' : null)
                ->summarize([
                    Summarizer::make('total_amount_sum')
                        ->label(__('labels.total'))
                        ->using(fn (): float => $this->sumTotalAmount())
                        ->numeric()
                        ->money('EUR'),
                ]),
            TextColumn::make('closing_balance')
                ->label(__('labels.closing_balance'))
                ->money('EUR')
                ->alignEnd()
                ->sortable()
                ->summarize([
                    Summarizer::make('total_closing_balance')
                        ->label(__('labels.total'))
                        ->using(fn (): float => $this->sumClosingBalance())
                        ->numeric()
                        ->money('EUR'),
                ]),
            TextColumn::make('budget_difference')
                ->label(__('labels.budget_difference'))
                ->money('EUR')
                ->alignEnd()
                ->sortable(),
        ]);
}
```

Replace `getTableQuery()` with the version that selects `budget_amount`, `closing_balance` and `budget_difference` aliases (and groups by `cb.budget_amount` too):

```php
private function getTableQuery(): Builder
{
    return CostCenter::query()
        ->leftJoin('cost_center_budgets as cb', function ($join): void {
            $join->on('cb.cost_center_id', '=', 'cost_centers.id')
                ->where('cb.year', '=', $this->selectedYear);
        })
        ->leftJoin('bookkeeping_records as br', function ($join): void {
            $join->on('br.cost_center_id', '=', 'cost_centers.id')
                ->where('br.year', '=', $this->selectedYear);
        })
        ->groupBy('cost_centers.id', 'cost_centers.number', 'cost_centers.title', 'cb.starting_amount', 'cb.budget_amount')
        ->select(
            'cost_centers.id',
            'cost_centers.number',
            'cost_centers.title',
            DB::raw('COALESCE(cb.starting_amount, 0) as starting_amount'),
            DB::raw('COALESCE(cb.budget_amount, 0) as budget_amount'),
            DB::raw('COALESCE(SUM(br.amount_price), 0) as total_amount'),
            DB::raw('COALESCE(cb.starting_amount, 0) + COALESCE(SUM(br.amount_price), 0) as closing_balance'),
            DB::raw('COALESCE(cb.budget_amount, 0) - COALESCE(SUM(br.amount_price), 0) as budget_difference'),
        )
        ->orderBy('cost_centers.number');
}
```

Add the total helpers (they re-evaluate on every table render, so they stay correct when `selectedYear` changes):

```php
private function sumBudgetAmount(): float
{
    return (float) CostCenterBudget::query()
        ->where('year', $this->selectedYear)
        ->sum('budget_amount');
}

private function sumStartingAmount(): float
{
    return (float) CostCenterBudget::query()
        ->where('year', $this->selectedYear)
        ->sum('starting_amount');
}

private function sumTotalAmount(): float
{
    return (float) BookkeepingRecord::query()
        ->where('year', $this->selectedYear)
        ->sum('amount_price');
}

private function sumClosingBalance(): float
{
    return $this->sumStartingAmount() + $this->sumTotalAmount();
}
```

> **Why custom summarizers instead of `Sum::make()`**: `Sum` emits `SUM(alias)`, which is invalid against the `DB::raw` aliased columns in this grouped query. Custom `Summarizer::make()->using()` computes the whole-year total with dedicated Eloquent aggregate queries (same proven pattern as `RunningTotalSummery`), which also keeps totals reactive to the year selector.

> **`result` label**: `labels.result` (`Resultaat`) becomes unused after this rename. It can be left in `lang/nl/labels.php` (harmless) or removed — no code references it afterwards.

---

## Change 8 — Dutch labels

File: `lang/nl/labels.php`

Add near the existing bookkeeping labels (after `'result' => 'Resultaat',` around line 235):

```php
'budget' => 'Budget',
'budgets' => 'Budgets',
'budget_difference' => 'Verschil',
```

Reuse existing keys (no change): `'starting_amount' => 'Startbedrag'`, `'closing_balance' => 'Eindsaldo'`, `'total' => 'Totaal'`.

---

## Change 9 — Tests

### Relation manager

File: `tests/Feature/Filament/CostCenters/CostCenterBudgetsRelationManagerTest.php` (new)

```bash
./Taskfile artisan make:test --phpunit CostCenterBudgetsRelationManagerTest
```

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\CostCenters;

use App\Filament\Admin\Resources\CostCenters\Pages\EditCostCenter;
use App\Filament\Admin\Resources\CostCenters\RelationManagers\CostCenterBudgetsRelationManager;
use App\Models\CostCenter;
use App\Models\CostCenterBudget;
use Livewire\Livewire;
use Tests\Concerns\WithAuthorizedUser;
use Tests\FeatureTestCase;

final class CostCenterBudgetsRelationManagerTest extends FeatureTestCase
{
    use WithAuthorizedUser;

    public function test_lists_budgets_for_the_cost_center(): void
    {
        $this->withAuthorizedUser();
        $costCenter = CostCenter::factory()->create();
        $budget = CostCenterBudget::factory()->create(['cost_center_id' => $costCenter->id]);

        Livewire::test(CostCenterBudgetsRelationManager::class, [
            'ownerRecord' => $costCenter,
            'pageClass' => EditCostCenter::class,
        ])
            ->assertCanSeeTableRecords([$budget]);
    }

    public function test_can_create_budget(): void
    {
        $this->withAuthorizedUser();
        $costCenter = CostCenter::factory()->create();

        Livewire::test(CostCenterBudgetsRelationManager::class, [
            'ownerRecord' => $costCenter,
            'pageClass' => EditCostCenter::class,
        ])
            ->callTableAction('create', data: [
                'year' => 2027,
                'starting_amount' => 1000,
                'budget_amount' => 5000,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('cost_center_budgets', [
            'cost_center_id' => $costCenter->id,
            'year' => 2027,
            'starting_amount' => 1000,
            'budget_amount' => 5000,
        ]);
    }

    public function test_can_edit_budget(): void
    {
        $this->withAuthorizedUser();
        $costCenter = CostCenter::factory()->create();
        $budget = CostCenterBudget::factory()->create([
            'cost_center_id' => $costCenter->id,
            'year' => 2026,
            'starting_amount' => 1000,
            'budget_amount' => 5000,
        ]);

        Livewire::test(CostCenterBudgetsRelationManager::class, [
            'ownerRecord' => $costCenter,
            'pageClass' => EditCostCenter::class,
        ])
            ->callTableAction('edit', $budget, data: [
                'budget_amount' => 7500,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('cost_center_budgets', [
            'id' => $budget->id,
            'budget_amount' => 7500,
        ]);
    }

    public function test_can_delete_budget(): void
    {
        $this->withAuthorizedUser();
        $costCenter = CostCenter::factory()->create();
        $budget = CostCenterBudget::factory()->create(['cost_center_id' => $costCenter->id]);

        Livewire::test(CostCenterBudgetsRelationManager::class, [
            'ownerRecord' => $costCenter,
            'pageClass' => EditCostCenter::class,
        ])
            ->callTableAction('delete', $budget);

        $this->assertDatabaseMissing('cost_center_budgets', ['id' => $budget->id]);
    }

    public function test_cannot_create_duplicate_budget_for_same_year(): void
    {
        $this->withAuthorizedUser();
        $costCenter = CostCenter::factory()->create();
        CostCenterBudget::factory()->create([
            'cost_center_id' => $costCenter->id,
            'year' => 2026,
        ]);

        Livewire::test(CostCenterBudgetsRelationManager::class, [
            'ownerRecord' => $costCenter,
            'pageClass' => EditCostCenter::class,
        ])
            ->callTableAction('create', data: [
                'year' => 2026,
                'starting_amount' => 1000,
                'budget_amount' => 5000,
            ])
            ->assertHasTableActionErrors(['year' => 'unique']);
    }
}
```

### Results page

File: `tests/Feature/Filament/Pages/CostCenterResultsPageTest.php` (new)

```bash
./Taskfile artisan make:test --phpunit CostCenterResultsPageTest
```

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Pages;

use App\Filament\Admin\Pages\CostCenterResults;
use App\Models\BookkeepingRecord;
use App\Models\CostCenter;
use App\Models\CostCenterBudget;
use Livewire\Livewire;
use Tests\Concerns\WithAuthorizedUser;
use Tests\FeatureTestCase;

final class CostCenterResultsPageTest extends FeatureTestCase
{
    use WithAuthorizedUser;

    public function test_whole_year_totals(): void
    {
        $this->withAuthorizedUser();
        $year = now()->year;

        $a = CostCenter::factory()->create();
        $b = CostCenter::factory()->create();

        CostCenterBudget::factory()->createMany([
            ['cost_center_id' => $a->id, 'year' => $year, 'starting_amount' => 1000, 'budget_amount' => 5000],
            ['cost_center_id' => $b->id, 'year' => $year, 'starting_amount' => 500, 'budget_amount' => 2500],
            ['cost_center_id' => $a->id, 'year' => $year, 'amount_price' => 2000, 'amount_vat' => 0],
            ['cost_center_id' => $b->id, 'year' => $year, 'amount_price' => 300, 'amount_vat' => 0],
        ]);

        Livewire::test(CostCenterResults::class)
            ->assertTableColumnSummarySet('budget_amount', 'total_budget', 7500.00)
            ->assertTableColumnSummarySet('total_amount', 'total_amount_sum', 2300.00)
            ->assertTableColumnSummarySet('closing_balance', 'total_closing_balance', 3800.00);
    }

    public function test_total_amount_is_rendered_red_when_over_budget(): void
    {
        $this->withAuthorizedUser();
        $year = now()->year;

        $costCenter = CostCenter::factory()->create(['number' => '1000']);
        CostCenterBudget::factory()->create([
            'cost_center_id' => $costCenter->id, 'year' => $year,
            'starting_amount' => 0, 'budget_amount' => 100,
        ]);
        BookkeepingRecord::factory()->create([
            'cost_center_id' => $costCenter->id, 'year' => $year,
            'amount_price' => 150, 'amount_vat' => 0,
        ]);

        Livewire::test(CostCenterResults::class)
            ->assertSeeHtml('fi-color-danger');
    }
}
```

> The red-colour assertion (`fi-color-danger`) verifies the danger text-colour class is emitted for the over-budget cell. If the exact class string differs in the rendered markup, assert for the formatted over-budget total or inspect the cell HTML manually.

### Existing tests

No existing tests reference the `result` column on the page, and the domain `CostCenterYearResult` / `BookkeepingRecordDbRepository::getResultsForYear()` are unchanged, so `CostCenterYearResultTest` and `BookkeepingRecordDbRepositoryTest` remain green. The `CostCenterResourceTest` is unaffected.

---

## Run checks

```bash
./Taskfile artisan migrate
./Taskfile artisan test --compact --filter=CostCenterBudgetsRelationManager
./Taskfile artisan test --compact --filter=CostCenterResultsPage
./Taskfile fix
./Taskfile stan
./Taskfile lint
./Taskfile test
```

Then ask the user whether to run the full suite.

---

## Summary of files

### New files
| File | Purpose |
|------|---------|
| `database/migrations/____add_budget_amount_to_cost_center_budgets_table.php` | Add `budget_amount` column |
| `app/Filament/Admin/Resources/CostCenters/RelationManagers/CostCenterBudgetsRelationManager.php` | Budget CRUD relation manager |
| `tests/Feature/Filament/CostCenters/CostCenterBudgetsRelationManagerTest.php` | Relation manager tests |
| `tests/Feature/Filament/Pages/CostCenterResultsPageTest.php` | Results page tests |

### Modified files
| File | Change |
|------|--------|
| `app/Models/CostCenterBudget.php` | Add `budget_amount` fillable + casts |
| `database/factories/CostCenterBudgetFactory.php` | Add `budget_amount` |
| `app/Filament/Admin/Resources/CostCenters/Schemas/CostCenterBudgetForm.php` | Add `budget_amount` field |
| `app/Filament/Admin/Resources/CostCenters/CostCenterResource.php` | Add `getRelations()` |
| `app/Filament/Admin/Pages/CostCenterResults.php` | Budget column, closing balance, difference, red colouring, totals |
| `lang/nl/labels.php` | Add `budget`, `budgets`, `budget_difference` |
