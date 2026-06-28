# Implementation Plan: Bookkeeping Solution — Auto-creation, Budgets & Fiscal Year Overview

## Goal

Extend the existing bookkeeping solution with three capabilities:

1. **Auto-create bookkeeping records** when invoices are marked pending (during batch close), using domain repositories.
2. **Cost center starting budgets per fiscal year** — a new `cost_center_budgets` table so each cost center can have a starting budget per year.
3. **Fiscal year results overview** — a custom Filament page to select a fiscal year and see, per cost center: starting budget + sum of all bookkeeping records = result.

---

## Context & Key Findings

| Aspect | Current State |
|--------|---------------|
| `BookkeepingRecord` model | `app/Models/BookkeepingRecord.php` — `year`, `cost_center_id`, `amount_price`, `amount_vat`, `description`, polymorphic `reference`. `CompoundPrice` accessor on `amount`. |
| `CostCenter` model | `app/Models/CostCenter.php` — `number`, `title`, `description`. Has `billableItems`, `invoiceLines` HasMany. |
| `InvoiceLine` model | `app/Models/InvoiceLine.php` — has `cost_center_id`, `subTotal` accessor returns `CompoundPrice` (`price*qty`, `vat*qty`). |
| `markInvoicesAsPending` | Lives in `InvoiceBatchRepository` interface / `InvoiceBatchRepositoryDb` impl (line 102). Batch `UPDATE` on invoices. |
| `closeBatch` flow | `InvoiceBatchServiceImpl::closeBatch()` (line 32): calls `markInvoicesAsPending` → `closeBatch` → dispatches `InvoiceBatchClosed` event. |
| `BookkeepingRecordRepository` | **Does not exist** — must be created. |
| `CostCenterRepository` | **Does not exist** — not needed for this plan (budgets managed via Filament relation manager). |
| Fiscal year concept | **Does not exist**. `year` is a plain integer column on `bookkeeping_records`. No model/enum/table. We keep it as an integer — no FiscalYear entity needed. |
| Repository pattern | Domain interface (`#[Autowire]`) in `app/Domain/<Context>/` + `final class ...DbRepository` (or `...RepositoryDb`) in `app/Infrastructure/<Context>/`. `#[Override]` on all methods. DB transactions for writes. Returns domain IDs/value objects. |
| Event/Listener pattern | `InvoiceBatchClosed` event dispatched from service. Listeners auto-discovered (live under `app/Domain/`, scanned by `bootstrap/app.php`). Listeners inject repositories via constructor. |
| Filament custom pages | Auto-discovered from `app/Filament/Admin/Pages/` (see `AdminPanelProvider` line 45). |
| Filament widgets | `StatsOverviewWidget` pattern for aggregation (see `BatchStatsOverview`, `MemberOverview`). |
| Tests | Unit tests use Expectation classes (Mockery). Feature tests use `WithAuthorizedUser` trait. |
| Permissions | `ResourcePermission` enum + `ResourcePolicy` base + `RolePermissionSeeder`. |

### Design Decisions

1. **Where to create bookkeeping records**: In `InvoiceBatchServiceImpl::closeBatch()`, after `markInvoicesAsPending`, call `BookkeepingRecordRepository::createForBatch($batchId)`. This keeps orchestration in the domain service and persistence in the repository — consistent with the existing `markInvoicesAsPending` + `closeBatch` pattern.
2. **Granularity of bookkeeping records**: One bookkeeping record **per invoice per cost center**, summing the subtotals of lines for that cost center. The `reference` morph points to the `Invoice`. The `year` is derived from the batch's invoice date. This keeps records at a meaningful granularity (one per cost center per invoice) while leveraging the existing `cost_center_id` column.
3. **Fiscal year as integer**: No `FiscalYear` model/enum. The `year` integer column on `bookkeeping_records` and the new `cost_center_budgets.year` column are sufficient. Available years for the overview selector are derived from the union of distinct years in both tables plus the current year.
4. **Budget management via RelationManager**: A `CostCenterBudgetsRelationManager` on the `CostCenterResource` edit page, rather than a separate resource. This keeps budgets contextual to their cost center.
5. **Overview as custom Filament Page**: A `CostCenterResults` page in `app/Filament/Admin/Pages/` (auto-discovered), with a year `Select` form and a `Table` widget showing per-cost-center results. Uses a read-only view repository for the aggregated query.

---

## Phase 1: Database Migrations

### Step 1.1 — Create `cost_center_budgets` table

```bash
./Taskfile artisan make:migration create_cost_center_budgets_table --no-interaction
```

File: `database/migrations/YYYY_MM_DD_HHMMSS_create_cost_center_budgets_table.php`

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
        Schema::create('cost_center_budgets', static function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
            $table->year('year');
            $table->foreignId('cost_center_id')->constrained('cost_centers')->cascadeOnDelete();
            $table->decimal('starting_amount', 10, 3)->default(0);

            $table->unique(['cost_center_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_center_budgets');
    }
};
```

> The `unique(['cost_center_id', 'year'])` constraint ensures one budget per cost center per fiscal year.

---

## Phase 2: Domain Layer

### Step 2.1 — Create `BookkeepingRecordId` value object

File: `app/Domain/Bookkeeping/BookkeepingRecordId.php`

```php
<?php

declare(strict_types=1);

namespace App\Domain\Bookkeeping;

use App\Domain\NumericId;

final readonly class BookkeepingRecordId extends NumericId {}
```

### Step 2.2 — Create `BookkeepingRecordRepository` interface

File: `app/Domain/Bookkeeping/BookkeepingRecordRepository.php`

```php
<?php

declare(strict_types=1);

namespace App\Domain\Bookkeeping;

use App\Domain\Invoices\InvoiceBatchId;
use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface BookkeepingRecordRepository
{
    /**
     * Create bookkeeping records for all pending invoices in the given batch.
     * Records are created per invoice per cost center, summing line subtotals.
     */
    public function createForBatch(InvoiceBatchId $batchId): void;

    /**
     * @return list<CostCenterYearResult>
     */
    public function getResultsForYear(int $year): array;
}
```

### Step 2.3 — Create `CostCenterYearResult` DTO

File: `app/Domain/Bookkeeping/CostCenterYearResult.php`

```php
<?php

declare(strict_types=1);

namespace App\Domain\Bookkeeping;

use App\Domain\Invoices\Billing\CostCenterId;
use App\Domain\Invoices\CompoundPrice;
use App\Domain\NumericId;

final readonly class CostCenterYearResult
{
    public function __construct(
        public CostCenterId $costCenterId,
        public string $number,
        public string $title,
        public float $startingAmount,
        public CompoundPrice $runningTotal,
    ) {}

    public function result(): CompoundPrice
    {
        // Result = starting budget + bookkeeping total
        // Starting budget is a price-only value (no VAT), so vat = 0
        return $this->runningTotal->add(
            new CompoundPrice($this->startingAmount, 0.0),
        );
    }
}
```

> The `result()` method returns the starting budget plus all bookkeeping record amounts for that cost center in that year. This represents the "result" of the cost center (budget + actuals).

---

## Phase 3: Infrastructure Layer

### Step 3.1 — Create `BookkeepingRecordRepositoryDb` implementation

File: `app/Infrastructure/Bookkeeping/BookkeepingRecordRepositoryDb.php`

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Bookkeeping;

use App\Domain\Bookkeeping\CostCenterYearResult;
use App\Domain\Bookkeeping\BookkeepingRecordRepository;
use App\Domain\Invoices\Billing\CostCenterId;
use App\Domain\Invoices\CompoundPrice;
use App\Domain\Invoices\InvoiceBatchId;
use App\Domain\Invoices\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Override;

final class BookkeepingRecordRepositoryDb implements BookkeepingRecordRepository
{
    #[Override]
    public function createForBatch(InvoiceBatchId $batchId): void
    {
        // Query pending invoices for this batch, joined with lines, grouped by
        // invoice + cost center, summing subtotals.
        $rows = Invoice::query()
            ->where('invoices.invoice_batch_id', $batchId->value)
            ->where('invoices.status', InvoiceStatus::Pending)
            ->joinRelationship('lines')
            ->joinRelationship('invoiceBatch')
            ->groupBy(
                'invoices.id',
                'invoices.invoice_number',
                'invoice_lines.cost_center_id',
                'invoice_batches.invoice_date',
            )
            ->select(
                'invoices.id as invoice_id',
                'invoices.invoice_number',
                'invoice_lines.cost_center_id',
                DB::raw('SUM(invoice_lines.price * invoice_lines.quantity) as total_price'),
                DB::raw('SUM(invoice_lines.vat * invoice_lines.quantity) as total_vat'),
                DB::raw('EXTRACT(YEAR FROM invoice_batches.invoice_date) as year'),
            )
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        $now = now();
        DB::table('bookkeeping_records')->insert(
            $rows->map(static fn (object $row) => [
                'year' => (int) $row->year,
                'cost_center_id' => $row->cost_center_id,
                'amount_price' => $row->total_price,
                'amount_vat' => $row->total_vat,
                'description' => 'Invoice ' . $row->invoice_number,
                'reference_type' => Invoice::class,
                'reference_id' => $row->invoice_id,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all(),
        );
    }

    /** @return list<CostCenterYearResult> */
    #[Override]
    public function getResultsForYear(int $year): array
    {
        $rows = DB::table('cost_centers as cc')
            ->leftJoin('cost_center_budgets as cb', function ($join) use ($year): void {
                $join->on('cb.cost_center_id', '=', 'cc.id')
                    ->where('cb.year', '=', $year);
            })
            ->leftJoin('bookkeeping_records as br', function ($join) use ($year): void {
                $join->on('br.cost_center_id', '=', 'cc.id')
                    ->where('br.year', '=', $year);
            })
            ->groupBy('cc.id', 'cc.number', 'cc.title', 'cb.starting_amount')
            ->orderBy('cc.number')
            ->select(
                'cc.id',
                'cc.number',
                'cc.title',
                DB::raw('COALESCE(cb.starting_amount, 0) as starting_amount'),
                DB::raw('COALESCE(SUM(br.amount_price), 0) as total_price'),
                DB::raw('COALESCE(SUM(br.amount_vat), 0) as total_vat'),
            )
            ->get();

        return $rows->map(static fn (object $row) => new CostCenterYearResult(
            costCenterId: CostCenterId::create((int) $row->id),
            number: $row->number,
            title: $row->title,
            startingAmount: (float) $row->starting_amount,
            totalBookkeeping: new CompoundPrice((float) $row->total_price, (float) $row->total_vat),
        ))->all();
    }
}
```

> **Note on `EXTRACT(YEAR FROM ...)`**: This is PostgreSQL syntax (the app uses `pgsql`). If cross-database compatibility is ever needed, use `CAST(invoice_batches.invoice_date AS INT)` equivalent, but since the engine is `pgsql`, `EXTRACT` is correct.

> **Batch insert**: Uses a single `DB::table()->insert([...])` call with an array of rows — no loop. This is both cleaner and more efficient than per-row inserts.

---

## Phase 4: Wire Bookkeeping Creation into Batch Close Flow

### Step 4.1 — Update `InvoiceBatchService` interface

File: `app/Domain/Invoices/InvoiceBatchService.php`

No interface change needed — `closeBatch` signature stays the same. The new dependency is internal to the implementation.

### Step 4.2 — Update `InvoiceBatchServiceImpl`

File: `app/Domain/Invoices/InvoiceBatchServiceImpl.php`

Add `BookkeepingRecordRepository` to the constructor and call it in `closeBatch`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Invoices;

use App\Domain\Bookkeeping\BookkeepingRecordRepository;
use App\Domain\Invoices\Events\InvoiceBatchClosed;
use DateTimeInterface;
use Illuminate\Contracts\Events\Dispatcher;
use Override;

final readonly class InvoiceBatchServiceImpl implements InvoiceBatchService
{
    public function __construct(
        private InvoiceBatchRepository $batchRepository,
        private BookkeepingRecordRepository $bookkeepingRepository,
        private Dispatcher $eventDispatcher,
    ) {}

    #[Override]
    public function createBatch(DateTimeInterface $invoiceDate): InvoiceBatchId
    {
        return $this->batchRepository->create($invoiceDate, InvoiceBatchStatus::Open);
    }

    #[Override]
    public function attachBatchMonth(InvoiceBatchId $batchId): void
    {
        $this->batchRepository->addOpenInvoicesFromBatchMonth($batchId);
    }

    #[Override]
    public function closeBatch(InvoiceBatchId $batchId): void
    {
        $this->batchRepository->markInvoicesAsPending($batchId);
        $this->bookkeepingRepository->createForBatch($batchId);
        $this->batchRepository->closeBatch($batchId);

        $this->eventDispatcher->dispatch(new InvoiceBatchClosed(batchId: $batchId));
    }

    /**
     * @throws \DomainException
     */
    #[Override]
    public function completeBatch(InvoiceBatchId $batchId): void
    {
        $this->batchRepository->completeBatch($batchId);
    }
}
```

> The order is important: `markInvoicesAsPending` sets invoices to `Pending` status, then `createForBatch` queries for `Pending` invoices in the batch and creates bookkeeping records, then `closeBatch` sets the batch status to `Pending`.

---

## Phase 5: Eloquent Model for Cost Center Budgets

### Step 5.1 — Create `CostCenterBudget` model

```bash
./Taskfile artisan make:model CostCenterBudget --no-interaction
```

File: `app/Models/CostCenterBudget.php`

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['year', 'cost_center_id', 'starting_amount'])]
final class CostCenterBudget extends Model
{
    use HasFactory;

    /** @return BelongsTo<CostCenter, $this> */
    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }
}
```

### Step 5.2 — Add `budgets` relation to `CostCenter` model

File: `app/Models/CostCenter.php`

Add a `budgets` HasMany relation:

```php
/** @return HasMany<CostCenterBudget, $this> */
public function budgets(): HasMany
{
    return $this->hasMany(CostCenterBudget::class);
}
```

Add imports: `use App\Models\CostCenterBudget;` is not needed (same namespace). Add `HasMany` is already imported.

---

## Phase 6: Factories

### Step 6.1 — Create `CostCenterBudgetFactory`

```bash
./Taskfile artisan make:factory CostCenterBudgetFactory --model=CostCenterBudget --no-interaction
```

File: `database/factories/CostCenterBudgetFactory.php`

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CostCenter;
use App\Models\CostCenterBudget;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<CostCenterBudget>
 */
final class CostCenterBudgetFactory extends Factory
{
    #[Override]
    public function definition(): array
    {
        return [
            'year' => now()->year,
            'cost_center_id' => CostCenter::factory(),
            'starting_amount' => fake()->randomFloat(2, 0, 10000),
        ];
    }
}
```

### Step 6.2 — Update `BookkeepingRecordFactory`

File: `database/factories/BookkeepingRecordFactory.php`

Fill in the empty definition:

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\BookkeepingRecord;
use App\Models\CostCenter;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<BookkeepingRecord>
 */
final class BookkeepingRecordFactory extends Factory
{
    #[Override]
    public function definition(): array
    {
        $price = fake()->randomFloat(2, 1, 500);

        return [
            'year' => now()->year,
            'cost_center_id' => CostCenter::factory(),
            'amount_price' => $price,
            'amount_vat' => $price * 0.21,
            'description' => fake()->sentence(),
        ];
    }
}
```

> Make the class `final` (it currently is not) to match the project convention.

---

## Phase 7: Filament — Cost Center Budgets Relation Manager

### Step 7.1 — Create `CostCenterBudgetsRelationManager`

File: `app/Filament/Admin/Resources/CostCenters/RelationManagers/CostCenterBudgetsRelationManager.php`

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\CostCenters\RelationManagers;

use App\Filament\Admin\Resources\CostCenters\Schemas\CostCenterBudgetForm;
use App\Filament\Admin\Resources\CostCenters\Tables\CostCenterBudgetsTable;
use App\Models\CostCenterBudget;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Override;

final class CostCenterBudgetsRelationManager extends RelationManager
{
    protected static string $relationship = 'budgets';

    protected static ?string $title = 'Budgets';

    #[Override]
    public function form(Schema $schema): Schema
    {
        return CostCenterBudgetForm::configure($schema);
    }

    #[Override]
    public function table(Table $table): Table
    {
        return CostCenterBudgetsTable::configure($table);
    }
}
```

### Step 7.2 — Create `CostCenterBudgetForm` schema

File: `app/Filament/Admin/Resources/CostCenters/Schemas/CostCenterBudgetForm.php`

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\CostCenters\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

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
                            ->maxValue(2100),
                        TextInput::make('starting_amount')
                            ->label(__('labels.starting_amount'))
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01),
                    ]),
            ]);
    }
}
```

### Step 7.3 — Create `CostCenterBudgetsTable`

File: `app/Filament/Admin/Resources/CostCenters/Tables/CostCenterBudgetsTable.php`

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\CostCenters\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class CostCenterBudgetsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('year')
                    ->label(__('labels.book_year'))
                    ->sortable(),
                TextColumn::make('starting_amount')
                    ->label(__('labels.starting_amount'))
                    ->money('EUR')
                    ->sortable(),
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

### Step 7.4 — Attach RelationManager to `EditCostCenter`

File: `app/Filament/Admin/Resources/CostCenters/Pages/EditCostCenter.php`

Update to include the relation manager:

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\CostCenters\Pages;

use App\Filament\Admin\Resources\CostCenters\CostCenterResource;
use App\Filament\Admin\Resources\CostCenters\RelationManagers\CostCenterBudgetsRelationManager;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Override;

final class EditCostCenter extends EditRecord
{
    protected static string $resource = CostCenterResource::class;

    #[Override]
    public function getRelationManagers(): array
    {
        return [
            CostCenterBudgetsRelationManager::make(),
        ];
    }

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

## Phase 8: Filament — Cost Center Results Overview Page

### Step 8.1 — Create `CostCenterResults` custom page

This page is auto-discovered from `app/Filament/Admin/Pages/` (see `AdminPanelProvider` line 45).

File: `app/Filament/Admin/Pages/CostCenterResults.php`

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Navigation\NavigationGroup;use Filament\Forms\Components\Select;use Filament\Forms\Concerns\InteractsWithForms;use Filament\Forms\Contracts\HasForms;use Filament\Forms\Form;use Filament\Pages\Page;use Filament\Support\Icons\Heroicon;use Filament\Tables\Columns\TextColumn;use Filament\Tables\Concerns\InteractsWithTable;use Filament\Tables\Contracts\HasTable;use Filament\Tables\Table;use Illuminate\Database\Eloquent\Builder;use Override;use UnitEnum;

final class CostCenterResults extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static string $view = 'filament.admin.pages.cost-center-results';

    protected static ?string $navigationIcon = Heroicon::ChartBarSquare;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Invoicing;

    public ?int $selectedYear = null;

    #[Override]
    public function mount(): void
    {
        $this->selectedYear = (int) now()->year;
    }

    #[Override]
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('selectedYear')
                    ->label(__('labels.book_year'))
                    ->options(fn () => $this->getAvailableYears())
                    ->default(now()->year)
                    ->live()
                    ->afterStateUpdated(fn () => $this->resetTable()),
            ]);
    }

    /** @return array<string> */
    private function getAvailableYears(): array
    {
        $bookkeepingYears = \App\Models\BookkeepingRecord::query()
            ->select('year')
            ->distinct()
            ->pluck('year', 'year');

        $budgetYears = \App\Models\CostCenterBudget::query()
            ->select('year')
            ->distinct()
            ->pluck('year', 'year');

        $currentYear = collect([now()->year => now()->year]);

        return $bookkeepingYears
            ->merge($budgetYears)
            ->merge($currentYear)
            ->sortDesc()
            ->mapWithKeys(static fn ($year) => [$year => (string) $year])
            ->all();
    }

    #[Override]
    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => $this->getTableQuery())
            ->columns([
                TextColumn::make('number')
                    ->label(__('labels.number'))
                    ->sortable(),
                TextColumn::make('title')
                    ->label(__('labels.title')),
                TextColumn::make('starting_amount')
                    ->label(__('labels.starting_amount'))
                    ->money('EUR')
                    ->sortable(),
                TextColumn::make('total_price')
                    ->label(__('labels.total_price'))
                    ->money('EUR'),
                TextColumn::make('total_vat')
                    ->label(__('labels.total_vat'))
                    ->money('EUR'),
                TextColumn::make('result')
                    ->label(__('labels.result'))
                    ->money('EUR'),
            ]);
    }

    private function getTableQuery(): Builder
    {
        // The table uses a custom query that maps CostCenterYearResult DTOs
        // into a query-like structure. Since Filament's table expects a Builder,
        // we use a base CostCenter query and compute the aggregates via accessors.
        return \App\Models\CostCenter::query()
            ->leftJoin('cost_center_budgets as cb', function ($join): void {
                $join->on('cb.cost_center_id', '=', 'cost_centers.id')
                    ->where('cb.year', '=', $this->selectedYear);
            })
            ->leftJoin('bookkeeping_records as br', function ($join): void {
                $join->on('br.cost_center_id', '=', 'cost_centers.id')
                    ->where('br.year', '=', $this->selectedYear);
            })
            ->groupBy('cost_centers.id', 'cost_centers.number', 'cost_centers.title', 'cb.starting_amount')
            ->select(
                'cost_centers.id',
                'cost_centers.number',
                'cost_centers.title',
                \Illuminate\Support\Facades\DB::raw('COALESCE(cb.starting_amount, 0) as starting_amount'),
                \Illuminate\Support\Facades\DB::raw('COALESCE(SUM(br.amount_price), 0) as total_price'),
                \Illuminate\Support\Facades\DB::raw('COALESCE(SUM(br.amount_vat), 0) as total_vat'),
                \Illuminate\Support\Facades\DB::raw('COALESCE(cb.starting_amount, 0) + COALESCE(SUM(br.amount_price), 0) as result'),
            )
            ->orderBy('cost_centers.number');
    }
}
```

> **Design note**: The table query uses a raw SQL join approach directly on the `CostCenter` model, computing `starting_amount`, `total_price`, `total_vat`, and `result` as aliased columns. This avoids needing to map DTOs into a Filament table (which expects a Builder). The `BookkeepingRecordRepository::getResultsForYear()` method is still available for domain-level use and testing, but the Filament page uses the query directly for table pagination/sorting support.

> **Alternative**: If the raw-query-in-page approach is too complex, the page can instead use the repository's `getResultsForYear()` and render results in a `StatsOverviewWidget` or a simple HTML table in the Blade view. The table approach is preferred for sorting and potential export features.

### Step 8.2 — Create the Blade view

File: `resources/views/filament/admin/pages/cost-center-results.blade.php`

```blade
<x-filament-panels::page>
    <div class="space-y-6">
        <form wire:submit="refreshTable">
            {{ $this->form }}
        </form>

        {{ $this->table }}
    </div>
</x-filament-panels::page>
```

> The form contains the year `Select` which is `->live()`, so changing it triggers `resetTable()` to refresh the table below.

---

## Phase 9: Labels (Dutch translations)

File: `lang/nl/labels.php`

Add these entries (after the existing `bookkeeping_records` entry at line 208):

```php
'starting_amount' => 'Startbudget',
'total_price' => 'Totaal prijs',
'total_vat' => 'Totaal btw',
'result' => 'Resultaat',
'cost_center_results' => 'Kostenplaats resultaten',
```

---

## Phase 10: Permissions

### Step 10.1 — Add bookkeeping permissions to `ResourcePermission`

File: `app/Domain/Authorization/ResourcePermission.php`

Add after the Cost Centers block (line 141):

```php
// Bookkeeping Records
case ViewAnyBookkeepingRecords = 'view_any_bookkeeping_records';
case ViewBookkeepingRecords = 'view_bookkeeping_records';
case CreateBookkeepingRecords = 'create_bookkeeping_records';
case UpdateBookkeepingRecords = 'update_bookkeeping_records';
case DeleteBookkeepingRecords = 'delete_bookkeeping_records';
case DeleteAnyBookkeepingRecords = 'delete_any_bookkeeping_records';

// Cost Center Budgets
case ViewAnyCostCenterBudgets = 'view_any_cost_center_budgets';
case ViewCostCenterBudgets = 'view_cost_center_budgets';
case CreateCostCenterBudgets = 'create_cost_center_budgets';
case UpdateCostCenterBudgets = 'update_cost_center_budgets';
case DeleteCostCenterBudgets = 'delete_cost_center_budgets';
case DeleteAnyCostCenterBudgets = 'delete_any_cost_center_budgets';
```

### Step 10.2 — Create `BookkeepingRecordPolicy`

```bash
./Taskfile artisan make:policy BookkeepingRecordPolicy --no-interaction
```

File: `app/Policies/BookkeepingRecordPolicy.php`

```php
<?php

declare(strict_types=1);

namespace App\Policies;

final class BookkeepingRecordPolicy extends ResourcePolicy
{
    protected static function permissionPrefix(): string
    {
        return 'bookkeeping_records';
    }
}
```

### Step 10.3 — Create `CostCenterBudgetPolicy`

```bash
./Taskfile artisan make:policy CostCenterBudgetPolicy --no-interaction
```

File: `app/Policies/CostCenterBudgetPolicy.php`

```php
<?php

declare(strict_types=1);

namespace App\Policies;

final class CostCenterBudgetPolicy extends ResourcePolicy
{
    protected static function permissionPrefix(): string
    {
        return 'cost_center_budgets';
    }
}
```

### Step 10.4 — Update `RolePermissionSeeder`

File: `database/seeders/RolePermissionSeeder.php`

In `seedFinancialAdministration()` (line 70), add:

```php
$this->allPermissionsFor('bookkeeping_records'),
$this->allPermissionsFor('cost_center_budgets'),
```

In `seedMemberAdministration()` (line 48), add view-only access:

```php
$this->viewPermissionsFor('bookkeeping_records'),
```

> The `CostCenterResults` overview page should be accessible to anyone with `view_any_bookkeeping_records` or `view_any_cost_centers`. Add a `canView()` check on the page or rely on the navigation visibility. Since the page is auto-discovered (not a resource), add a `canAccess()` method to the page:

### Step 10.5 — Add access control to `CostCenterResults` page

In `app/Filament/Admin/Pages/CostCenterResults.php`, add:

```php
#[Override]
public static function canAccess(): bool
{
    return auth()->user()?->can('view_any_bookkeeping_records')
        || auth()->user()?->can('view_any_cost_centers')
        ?? false;
}
```

---

## Phase 11: Tests

### Step 11.1 — Create `BookkeepingRecordRepositoryExpectation`

File: `tests/Unit/Domain/Bookkeeping/BookkeepingRecordRepositoryExpectation.php`

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Bookkeeping;

use App\Domain\Bookkeeping\BookkeepingRecordRepository;
use App\Domain\Bookkeeping\CostCenterYearResult;
use App\Domain\Invoices\InvoiceBatchId;
use Mockery;
use Mockery\MockInterface;

use function PHPUnit\Framework\equalTo;

final readonly class BookkeepingRecordRepositoryExpectation
{
    private function __construct(
        public MockInterface&BookkeepingRecordRepository $mock,
    ) {}

    public static function create(): self
    {
        return new self(Mockery::mock(BookkeepingRecordRepository::class));
    }

    public function expectsCreateForBatch(InvoiceBatchId $batchId): void
    {
        $this->mock
            ->expects('createForBatch')
            ->with(equalTo($batchId));
    }

    /** @param list<CostCenterYearResult> $return */
    public function expectsGetResultsForYear(int $year, array $return): void
    {
        $this->mock
            ->expects('getResultsForYear')
            ->with(equalTo($year))
            ->andReturn($return);
    }
}
```

### Step 11.2 — Update `InvoiceBatchServiceTest`

File: `tests/Unit/Domain/Invoices/InvoiceBatchServiceTest.php`

Add `BookkeepingRecordRepositoryExpectation` to the test setup and update the `closeBatch` test:

```php
use App\Domain\Bookkeeping\BookkeepingRecordRepository;
use Tests\Unit\Domain\Bookkeeping\BookkeepingRecordRepositoryExpectation;

// In setUp():
$this->bookkeepingRepo = BookkeepingRecordRepositoryExpectation::create();
$this->service = new InvoiceBatchServiceImpl(
    $this->repo->mock,
    $this->bookkeepingRepo->mock,
    $this->dispatcher->mock,
);

// Update test_close_batch():
public function test_close_batch(): void
{
    $batchId = InvoiceBatchId::create(5);

    $this->repo->expectsMarkInvoicesAsPending($batchId);
    $this->bookkeepingRepo->expectsCreateForBatch($batchId);
    $this->repo->expectsCloseBatch($batchId);

    $this->dispatcher->expectsDispatch(new InvoiceBatchClosed(batchId: $batchId));

    $this->service->closeBatch($batchId);
}
```

### Step 11.3 — Feature test: `BookkeepingRecordRepositoryDb::createForBatch`

```bash
./Taskfile artisan make:test --phpunit BookkeepingRecordCreateForBatchTest
```

File: `tests/Feature/Infrastructure/Bookkeeping/BookkeepingRecordCreateForBatchTest.php`

Test that closing a batch creates bookkeeping records:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Bookkeeping;

use App\Domain\Bookkeeping\BookkeepingRecordRepository;
use App\Domain\Invoices\InvoiceBatchId;
use App\Models\BookkeepingRecord;
use App\Models\CostCenter;
use App\Models\Invoice;
use App\Models\InvoiceBatch;
use App\Models\InvoiceLine;
use App\Models\Member;
use Database\Factories\InvoiceFactory;
use Database\Factories\InvoiceLineFactory;
use Database\Factories\InvoiceBatchFactory;
use Database\Factories\CostCenterFactory;
use Override;
use Tests\Feature\FeatureTestCase;

final class BookkeepingRecordCreateForBatchTest extends FeatureTestCase
{
    private BookkeepingRecordRepository $repository;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = $this->app->make(BookkeepingRecordRepository::class);
    }

    public function test_create_for_batch_creates_records_per_cost_center(): void
    {
        $costCenterA = CostCenter::factory()->create();
        $costCenterB = CostCenter::factory()->create();
        $batch = InvoiceBatch::factory()->create(['invoice_date' => '2026-06-15']);

        $invoice = Invoice::factory()
            ->has(InvoiceLine::factory()->state(['cost_center_id' => $costCenterA->id, 'price' => 100, 'vat' => 21, 'quantity' => 2]))
            ->has(InvoiceLine::factory()->state(['cost_center_id' => $costCenterB->id, 'price' => 50, 'vat' => 10.5, 'quantity' => 1]))
            ->create([
                'invoice_batch_id' => $batch->id,
                'status' => 'pending',
            ]);

        $this->repository->createForBatch(InvoiceBatchId::create($batch->id));

        $records = BookkeepingRecord::query()->where('reference_type', Invoice::class)->get();

        static::assertCount(2, $records);
        static::assertTrue($records->contains(fn ($r) => $r->cost_center_id === $costCenterA->id && (float) $r->amount_price === 200.0));
        static::assertTrue($records->contains(fn ($r) => $r->cost_center_id === $costCenterB->id && (float) $r->amount_price === 50.0));
        static::assertSame(2026, $records->first()->year);
    }

    public function test_create_for_batch_no_pending_invoices_creates_nothing(): void
    {
        $batch = InvoiceBatch::factory()->create();

        $this->repository->createForBatch(InvoiceBatchId::create($batch->id));

        static::assertSame(0, BookkeepingRecord::query()->count());
    }
}
```

### Step 11.4 — Feature test: `BookkeepingRecordRepositoryDb::getResultsForYear`

```bash
./Taskfile artisan make:test --phpunit BookkeepingRecordGetResultsForYearTest
```

File: `tests/Feature/Infrastructure/Bookkeeping/BookkeepingRecordGetResultsForYearTest.php`

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Bookkeeping;

use App\Domain\Bookkeeping\BookkeepingRecordRepository;
use App\Models\BookkeepingRecord;
use App\Models\CostCenter;
use App\Models\CostCenterBudget;
use Override;
use Tests\Feature\FeatureTestCase;

final class BookkeepingRecordGetResultsForYearTest extends FeatureTestCase
{
    private BookkeepingRecordRepository $repository;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = $this->app->make(BookkeepingRecordRepository::class);
    }

    public function test_get_results_for_year_combines_budget_and_bookkeeping(): void
    {
        $costCenter = CostCenter::factory()->create();
        CostCenterBudget::factory()->create([
            'cost_center_id' => $costCenter->id,
            'year' => 2026,
            'starting_amount' => 5000,
        ]);
        BookkeepingRecord::factory()->create([
            'cost_center_id' => $costCenter->id,
            'year' => 2026,
            'amount_price' => 1200,
            'amount_vat' => 252,
        ]);

        $results = $this->repository->getResultsForYear(2026);

        static::assertCount(1, $results);
        $result = $results[0];
        static::assertSame($costCenter->id, $result->costCenterId->value);
        static::assertSame(5000.0, $result->startingAmount);
        static::assertSame(1200.0, $result->totalBookkeeping->price);
        static::assertSame(252.0, $result->totalBookkeeping->vat);
        static::assertSame(6200.0, $result->result()->price);
    }

    public function test_get_results_for_year_without_budget_defaults_to_zero(): void
    {
        $costCenter = CostCenter::factory()->create();
        BookkeepingRecord::factory()->create([
            'cost_center_id' => $costCenter->id,
            'year' => 2026,
            'amount_price' => 300,
            'amount_vat' => 63,
        ]);

        $results = $this->repository->getResultsForYear(2026);

        static::assertSame(0.0, $results[0]->startingAmount);
        static::assertSame(300.0, $results[0]->result()->price);
    }

    public function test_get_results_for_year_excludes_other_years(): void
    {
        $costCenter = CostCenter::factory()->create();
        BookkeepingRecord::factory()->create([
            'cost_center_id' => $costCenter->id,
            'year' => 2025,
            'amount_price' => 999,
        ]);
        BookkeepingRecord::factory()->create([
            'cost_center_id' => $costCenter->id,
            'year' => 2026,
            'amount_price' => 100,
        ]);

        $results = $this->repository->getResultsForYear(2026);

        static::assertSame(100.0, $results[0]->totalBookkeeping->price);
    }
}
```

### Step 11.5 — Feature test: `CostCenterBudgetsRelationManager`

```bash
./Taskfile artisan make:test --phpunit CostCenterBudgetRelationManagerTest
```

File: `tests/Feature/Filament/CostCenters/CostCenterBudgetRelationManagerTest.php`

Test that a financial admin can create, edit, and delete budgets from the cost center edit page. Follow the pattern from existing relation manager tests.

### Step 11.6 — Feature test: `CostCenterResults` page

```bash
./Taskfile artisan make:test --phpunit CostCenterResultsPageTest
```

File: `tests/Feature/Filament/Pages/CostCenterResultsPageTest.php`

Test that the page renders, the year select works, and the table shows correct data.

### Step 11.7 — Run tests

```bash
# New tests
./Taskfile artisan test --compact --filter=BookkeepingRecordCreateForBatch
./Taskfile artisan test --compact --filter=BookkeepingRecordGetResultsForYear
./Taskfile artisan test --compact --filter=CostCenterBudgetRelationManager
./Taskfile artisan test --compact --filter=CostCenterResultsPage

# Updated tests
./Taskfile artisan test --compact tests/Unit/Domain/Invoices/InvoiceBatchServiceTest.php

# Then ask user if they want to run the full suite
```

---

## Summary of all files to create/modify

### New files
| File | Purpose |
|------|---------|
| `database/migrations/____create_cost_center_budgets_table.php` | Budgets table |
| `app/Domain/Bookkeeping/BookkeepingRecordId.php` | Domain ID value object |
| `app/Domain/Bookkeeping/BookkeepingRecordRepository.php` | Repository interface |
| `app/Domain/Bookkeeping/CostCenterYearResult.php` | Result DTO |
| `app/Infrastructure/Bookkeeping/BookkeepingRecordRepositoryDb.php` | Repository implementation |
| `app/Models/CostCenterBudget.php` | Eloquent model |
| `database/factories/CostCenterBudgetFactory.php` | Factory |
| `app/Filament/Admin/Resources/CostCenters/RelationManagers/CostCenterBudgetsRelationManager.php` | Relation manager |
| `app/Filament/Admin/Resources/CostCenters/Schemas/CostCenterBudgetForm.php` | Budget form schema |
| `app/Filament/Admin/Resources/CostCenters/Tables/CostCenterBudgetsTable.php` | Budget table schema |
| `app/Filament/Admin/Pages/CostCenterResults.php` | Fiscal year overview page |
| `resources/views/filament/admin/pages/cost-center-results.blade.php` | Page Blade view |
| `app/Policies/BookkeepingRecordPolicy.php` | Policy |
| `app/Policies/CostCenterBudgetPolicy.php` | Policy |
| `tests/Unit/Domain/Bookkeeping/BookkeepingRecordRepositoryExpectation.php` | Mock expectation |
| `tests/Feature/Infrastructure/Bookkeeping/BookkeepingRecordCreateForBatchTest.php` | Feature test |
| `tests/Feature/Infrastructure/Bookkeeping/BookkeepingRecordGetResultsForYearTest.php` | Feature test |
| `tests/Feature/Filament/CostCenters/CostCenterBudgetRelationManagerTest.php` | Feature test |
| `tests/Feature/Filament/Pages/CostCenterResultsPageTest.php` | Feature test |

### Modified files
| File | Change |
|------|--------|
| `app/Domain/Invoices/InvoiceBatchServiceImpl.php` | Add `BookkeepingRecordRepository` dependency, call `createForBatch` in `closeBatch` |
| `app/Models/CostCenter.php` | Add `budgets()` HasMany relation |
| `database/factories/BookkeepingRecordFactory.php` | Fill in definition, make `final` |
| `app/Filament/Admin/Resources/CostCenters/Pages/EditCostCenter.php` | Attach `CostCenterBudgetsRelationManager` |
| `app/Domain/Authorization/ResourcePermission.php` | Add bookkeeping_records + cost_center_budgets permissions |
| `database/seeders/RolePermissionSeeder.php` | Assign new permissions to roles |
| `lang/nl/labels.php` | Add new labels |
| `tests/Unit/Domain/Invoices/InvoiceBatchServiceTest.php` | Add bookkeeping repo mock, update closeBatch test |

---

## Execution order

1. **Migrations** (Phase 1) — create `cost_center_budgets` table, run `./Taskfile artisan migrate`
2. **Domain layer** (Phase 2) — `BookkeepingRecordId`, `BookkeepingRecordRepository` interface, `CostCenterYearResult` DTO
3. **Infrastructure** (Phase 3) — `BookkeepingRecordRepositoryDb` implementation
4. **Wire into close flow** (Phase 4) — update `InvoiceBatchServiceImpl`
5. **Models** (Phase 5) — `CostCenterBudget` model, add `budgets()` to `CostCenter`
6. **Factories** (Phase 6) — `CostCenterBudgetFactory`, update `BookkeepingRecordFactory`
7. **Filament relation manager** (Phase 7) — budget management on cost center edit page
8. **Filament overview page** (Phase 8) — `CostCenterResults` page + Blade view
9. **Labels** (Phase 9) — Dutch translations
10. **Permissions** (Phase 10) — `ResourcePermission` enum + policies + seeder + page access
11. **Tests** (Phase 11) — write and run all tests

---

## Data flow diagram

```
InvoiceBatch closed (closeBatch action in Filament)
    │
    ▼
InvoiceBatchServiceImpl::closeBatch()
    ├──▶ InvoiceBatchRepository::markInvoicesAsPending()  [batch UPDATE invoices → pending]
    ├──▶ BookkeepingRecordRepository::createForBatch()    [NEW: batch INSERT bookkeeping_records]
    │       └── queries pending invoices + lines
    │           groups by invoice + cost_center
    │           sums price*qty, vat*qty
    │           inserts with reference → Invoice, year → batch date year
    ├──▶ InvoiceBatchRepository::closeBatch()             [batch → pending status]
    └──▶ dispatch InvoiceBatchClosed event
            └──▶ QueueInvoiceEmails listener (existing)

Cost Center Results Overview Page
    │
    ▼
CostCenterResults page (Filament, auto-discovered)
    ├── Year Select (derived from bookkeeping_records.year ∪ cost_center_budgets.year ∪ current year)
    └── Table query:
            cost_centers
              LEFT JOIN cost_center_budgets (WHERE year = selected)
              LEFT JOIN bookkeeping_records (WHERE year = selected)
            GROUP BY cost_center
            SELECT: number, title, starting_amount, SUM(amount_price), SUM(amount_vat), result

Cost Center Budget Management
    │
    ▼
EditCostCenter page
    └── CostCenterBudgetsRelationManager
            ├── List budgets (year, starting_amount)
            ├── Create budget (year + starting_amount)
            └── Edit/Delete budget
```
