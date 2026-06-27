# Implementation Plan: Purchase Orders

## Goal

Add the ability to track Purchase Orders (inkooporders). A purchase order has a description, a creditor name, a date, a status (open, pending, paid), an optional image file (e.g. a scanned receipt or supplier invoice), and multiple lines. Each line has a description, a net price, a VAT amount, and a cost center. Only the FinancialAdministration role can access purchase orders. When a purchase order transitions to pending or paid, its lines are added to `bookkeeping_records` — mirroring how invoice lines become bookkeeping records during batch close.

---

## Context & Key Findings

| Aspect | Current State |
|--------|---------------|
| `Invoice` model | `app/Models/Invoice.php` — `status` (InvoiceStatus enum), `invoice_number`, `date`, recipient fields, `member_id`, `invoice_batch_id`. HasMany `InvoiceLine`. `total` accessor sums line subtotals into `CompoundPrice`. |
| `InvoiceLine` model | `app/Models/InvoiceLine.php` — `description`, `price`, `vat`, `quantity`, `cost_center_id`. `#[Fillable]` attribute. `subTotal` and `compoundPrice` accessors return `CompoundPrice`. |
| `BookkeepingRecord` model | `app/Models/BookkeepingRecord.php` — `year`, `cost_center_id`, `amount_price`, `amount_vat`, `description`, polymorphic `reference` (morphTo). `amount` accessor maps to/from `CompoundPrice`. `#[Guarded]` attribute. |
| `CostCenter` model | `app/Models/CostCenter.php` — `number`, `title`, `description`. HasMany `billableItems`, `invoiceLines`, `budgets`. |
| Bookkeeping creation for invoices | `BookkeepingRecordDbRepository::createForBatch()` (line 21) — uses `insertUsing` with a subquery that joins `invoices` → `invoice_lines` → `invoice_batches`, groups by invoice + cost center, sums `price*qty` and `vat*qty`. Idempotent via `whereNotExists` on `bookkeeping_records.reference_id`. Year extracted from `invoice_batches.invoice_date`. |
| `BookkeepingRecordRepository` interface | `app/Domain/Bookkeeping/BookkeepingRecordRepository.php` — `#[Autowire]` interface with `createForBatch(InvoiceBatchId)` and `getResultsForYear(int)`. |
| Service pattern | `InvoiceBatchServiceImpl` (line 13) — readonly service with repository + event dispatcher injected. `closeBatch()` calls `markInvoicesAsPending` → `createForBatch` → `closeBatch` → dispatches event. No explicit DB transaction; idempotency is the safety net. |
| Repository pattern | Domain interface (`#[Autowire]`) in `app/Domain/<Context>/` + `final class ...DbRepository` (or `...RepositoryDb`) in `app/Infrastructure/<Context>/`. `#[Override]` on all methods. Returns domain IDs/value objects. |
| ID value objects | Extend `App\Domain\NumericId` (e.g., `InvoiceId`, `InvoiceBatchId`, `CostCenterId`). `final readonly class X extends NumericId {}`. |
| `CompoundPrice` | `app/Domain/Invoices/CompoundPrice.php` — readonly VO with `price` (float) and `vat` (float). `empty()`, `create(price, qty)`, `add()`. |
| Authorization | `ResourcePermission` enum — CRUD cases per resource. `ResourcePolicy` base — maps `permissionPrefix()` to `view_any_`, `view_`, `create_`, `update_`, `delete_`, `delete_any_`. `InvoicePolicy` overrides `update`/`delete` to also check `status === Open`. `RolePermissionSeeder` — `seedFinancialAdministration()` grants all permissions for invoices, cost_centers, bookkeeping_records, cost_center_budgets, invoice_batches. |
| Filament resource structure | `Resources/<Name>/<Name>Resource.php` + `Pages/` (Create, Edit, List, View) + `Schemas/<Name>Form.php` + `Tables/<Name>sTable.php`. Form uses `Section` + `Repeater` for lines. Table uses `TextColumn`, filters, tabs. |
| Filament status actions | `EditInvoiceBatch` (line 80) — `Action::make('closeBatch')` with `requiresConfirmation()`, injects service, calls service method, `successNotificationTitle`. Visibility via `->visible()` callback. |
| Filament list tabs | `ListInvoices` (line 20) — `getTabs()` returns tabs per status with `modifyQueryUsing`. |
| Labels | `lang/nl/labels.php` — all UI labels. `lang/nl/notifications.php` — notification messages. Only `nl` locale exists. |
| Navigation groups | `NavigationGroup` enum — `Invoicing`, `Bookkeeping`, `MemberAdministration`, `Rental`, `Activities`, `Technical`. |
| Status labels | `app/Filament/Admin/Labels/InvoiceStatusLabels.php` — static `options()` method returning `[status_value => __('labels.invoice_status.xxx')]`. |
| Tests — unit | `UnitTestCase` + Mockery expectation classes. `InvoiceBatchServiceTest` mocks repos + dispatcher, asserts call order. Expectation classes in `tests/Unit/Domain/<Context>/`. |
| Tests — feature | `FeatureTestCase` (LazilyRefreshDatabase, WithCachedAutowire). `WithAuthorizedUser` trait — `withUserHavingRole(RoleName)`, `withAuthorizedUser()` (full_access). Authorization tests use `Livewire::test(List...::class)->assertSuccessful/assertForbidden`. |
| Tests — bookkeeping repo | `BookkeepingRecordDbRepositoryTest` — instantiates repo directly, uses factories, asserts `BookkeepingRecord` rows. |
| `joinRelationship` | From `kirschbaum-development/eloquent-power-joins` v4.3. Works on Eloquent queries when the model has the relationship defined. No trait needed on the model. |
| `PriceFormatter` | `app/Formatters/PriceFormatter.php` — `format(float)`, `formatCompound(CompoundPrice)`, `formatCompoundSignless(?CompoundPrice)`, `parse(string)`. |
| `ViewOrEdit` utility | `app/Filament/Admin/Utils/ViewOrEdit.php` — `route($resource)` returns a closure for `recordUrl`, `routeFor($resource, $record)` returns a URL string. |
| File uploads | **No existing file uploads in the codebase.** Filament v5 `FileUpload` (FilePond-based) stores a file path string in a column. Default disk: `local` (private, `storage/app/private/`). Filament generates temporary signed URLs for private file previews (`temporary_file_url_expiry_minutes` = 30 in `config/filament.php`). `ImageColumn` in tables also supports private disks via signed URLs. `FileUpload::image()` restricts to image MIME types. `FileUpload` auto-deletes old file when replaced. |
| Filesystem config | `config/filesystems.php` — `local` disk (private, `storage_path('app/private')`), `public` disk (public, `storage_path('app/public')` with `storage:link` symlink). `FILESYSTEM_DISK=local` in `.env`. |
| Observer pattern | `#[ObservedBy(XObserver::class)]` PHP attribute on the model (Laravel 11+). Observers in `app/Observers/`. E.g. `MemberObserver` handles `created`/`updated` events. Used for side-effects on model lifecycle. |

### Design Decisions

1. **New `PurchaseOrders` domain** — `app/Domain/PurchaseOrders/` for the status enum, ID VO, service interface + impl. `app/Infrastructure/PurchaseOrders/` for the repository DB impl. This follows the existing domain/infrastructure split.

2. **`date` field on purchase orders** — The user listed description, creditor_name, and status. A `date` field is added because the bookkeeping `year` must be derived from a meaningful date (not `now()` or `created_at`), consistent with how invoices derive the year from `invoice_batches.invoice_date`. The date defaults to today on creation and is editable while the PO is open.

3. **Line fields: `price` + `price_vat` (no quantity)** — The user specified `price` and `price_vat` per line. Unlike invoice lines (which have unit `price`, unit `vat`, and `quantity`), purchase order lines store the total net price and total VAT directly. No quantity field. This matches the user's explicit field list and is appropriate for purchase orders where the line amount is the full amount from the creditor's invoice.

4. **`price_vat` is manual input** — Unlike invoice lines where `vat` is auto-calculated as `price * 0.21`, purchase order lines have a manually entered `price_vat`. This is necessary because external creditors may charge different VAT rates (0%, 9%, 21%) or the amounts may come from a supplier invoice with specific rounding.

5. **Bookkeeping creation via `BookkeepingRecordRepository::createForPurchaseOrder`** — A new method on the existing `BookkeepingRecordRepository` interface, implemented in `BookkeepingRecordDbRepository`. Uses the same `insertUsing` + `whereNotExists` idempotent pattern as `createForBatch`. Groups by purchase order + cost center, sums `price` and `price_vat`. Year extracted from `purchase_orders.date`. The `reference` morph points to the `PurchaseOrder` model.

6. **Status transitions via `PurchaseOrderService`** — `markAsPending(PurchaseOrderId)` and `markAsPaid(PurchaseOrderId)`. Each method: (1) updates the PO status via `PurchaseOrderRepository`, (2) calls `bookkeepingRepository->createForPurchaseOrder($id)`. The bookkeeping creation is idempotent — calling it on both transitions is safe; if records already exist (pending → paid), the `whereNotExists` check skips them. No explicit DB transaction, consistent with `InvoiceBatchServiceImpl::closeBatch()`.

7. **`PurchaseOrderPolicy` extends `ResourcePolicy`** — Override `update` and `delete` to require `status === Open`, mirroring `InvoicePolicy`. Other CRUD methods inherit from `ResourcePolicy` base.

8. **Navigation group: `Bookkeeping`** — Purchase orders are a bookkeeping/financial concern. Placed under the `Bookkeeping` navigation group alongside Cost Centers, Cost Center Budgets, and Bookkeeping Records.

9. **Filament form uses `Repeater` for lines** — Same pattern as `InvoiceForm`. The repeater uses `->relationship()` to manage `PurchaseOrderLine` records directly. Lines can only be edited while the PO is in `Open` status (enforced by the policy + form field visibility).

10. **Status transition actions on Edit page** — `markAsPending` action (visible when Open), `markAsPaid` action (visible when Pending). Both use `requiresConfirmation()` and inject `PurchaseOrderService`, following the `EditInvoiceBatch` action pattern.

11. **Image file storage on `local` (private) disk** — The `image_path` column stores the file path. Filament's `FileUpload` uses the default `local` disk (private) with a `purchase-orders` directory. Filament generates signed URLs for previews in the form and `ImageColumn` in the table. This keeps purchase order receipts private (only accessible to authenticated admin users) and requires no `storage:link` setup. A `PurchaseOrderObserver` on the `deleted` event cleans up the file from disk when a PO is deleted.

---

## Phase 1: Database Migrations

### Step 1.1 — Create `purchase_orders` table

```bash
./Taskfile artisan make:migration create_purchase_orders_table --no-interaction
```

File: `database/migrations/YYYY_MM_DD_HHMMSS_create_purchase_orders_table.php`

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
        Schema::create('purchase_orders', static function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
            $table->string('description');
            $table->string('creditor_name');
            $table->date('date');
            $table->string('status')->index()->default('open');
            $table->string('image_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
```

> The `status` column stores the `PurchaseOrderStatus` enum value as a string, defaulting to `open`. Indexed for tab filtering. Same pattern as `invoices.status`. The `image_path` column stores the file path on the `local` disk (nullable — image is optional).

### Step 1.2 — Create `purchase_order_lines` table

```bash
./Taskfile artisan make:migration create_purchase_order_lines_table --no-interaction
```

File: `database/migrations/YYYY_MM_DD_HHMMSS_create_purchase_order_lines_table.php`

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
        Schema::create('purchase_order_lines', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->string('description');
            $table->decimal('price', 10, 3);
            $table->decimal('price_vat', 10, 3);
            $table->foreignId('cost_center_id')->constrained('cost_centers');
            $table->timestamps();

            $table->index('cost_center_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_lines');
    }
};
```

> `price` = net amount, `price_vat` = VAT amount. Both decimal(10,3) matching invoice line precision. `cascadeOnDelete` on the PO FK so deleting a PO removes its lines. `cost_center_id` FK + index matching the `invoice_lines.cost_center_id` pattern.

---

## Phase 2: Domain Layer

### Step 2.1 — Create `PurchaseOrderStatus` enum

File: `app/Domain/PurchaseOrders/PurchaseOrderStatus.php`

```php
<?php

declare(strict_types=1);

namespace App\Domain\PurchaseOrders;

enum PurchaseOrderStatus: string
{
    case Open = 'open';
    case Pending = 'pending';
    case Paid = 'paid';
}
```

> Mirrors `InvoiceStatus` but without `Declined`. The three statuses match the user's specification.

### Step 2.2 — Create `PurchaseOrderId` value object

File: `app/Domain/PurchaseOrders/PurchaseOrderId.php`

```php
<?php

declare(strict_types=1);

namespace App\Domain\PurchaseOrders;

use App\Domain\NumericId;

final readonly class PurchaseOrderId extends NumericId {}
```

> Same pattern as `InvoiceId`, `InvoiceBatchId`, `CostCenterId`.

### Step 2.3 — Create `PurchaseOrderRepository` interface

File: `app/Domain/PurchaseOrders/PurchaseOrderRepository.php`

```php
<?php

declare(strict_types=1);

namespace App\Domain\PurchaseOrders;

use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface PurchaseOrderRepository
{
    public function markAsPending(PurchaseOrderId $id): void;

    public function markAsPaid(PurchaseOrderId $id): void;
}
```

> The repository handles status updates only. Bookkeeping record creation is delegated to `BookkeepingRecordRepository` by the service. This separation mirrors how `InvoiceBatchRepository` handles `markInvoicesAsPending` while `BookkeepingRecordRepository` handles `createForBatch`.

### Step 2.4 — Create `PurchaseOrderService` interface

File: `app/Domain/PurchaseOrders/PurchaseOrderService.php`

```php
<?php

declare(strict_types=1);

namespace App\Domain\PurchaseOrders;

use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface PurchaseOrderService
{
    public function markAsPending(PurchaseOrderId $id): void;

    public function markAsPaid(PurchaseOrderId $id): void;
}
```

### Step 2.5 — Create `PurchaseOrderServiceImpl`

File: `app/Domain/PurchaseOrders/PurchaseOrderServiceImpl.php`

```php
<?php

declare(strict_types=1);

namespace App\Domain\PurchaseOrders;

use App\Domain\Bookkeeping\BookkeepingRecordRepository;
use Override;

final readonly class PurchaseOrderServiceImpl implements PurchaseOrderService
{
    public function __construct(
        private PurchaseOrderRepository $repository,
        private BookkeepingRecordRepository $bookkeepingRepository,
    ) {}

    #[Override]
    public function markAsPending(PurchaseOrderId $id): void
    {
        $this->repository->markAsPending($id);
        $this->bookkeepingRepository->createForPurchaseOrder($id);
    }

    #[Override]
    public function markAsPaid(PurchaseOrderId $id): void
    {
        $this->repository->markAsPaid($id);
        $this->bookkeepingRepository->createForPurchaseOrder($id);
    }
}
```

> Both methods call `createForPurchaseOrder` — the idempotent `whereNotExists` check in the repository prevents duplicate records when transitioning pending → paid. This is the same safety pattern used by `createForBatch`.

---

## Phase 3: Models & Factories

### Step 3.1 — Create `PurchaseOrder` model

```bash
./Taskfile artisan make:model PurchaseOrder --no-interaction
```

File: `app/Models/PurchaseOrder.php`

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Invoices\CompoundPrice;
use App\Domain\PurchaseOrders\PurchaseOrderStatus;
use App\Observers\PurchaseOrderObserver;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Override;

/**
 * @property PurchaseOrderStatus $status
 * @property DateTimeInterface $date
 */
#[Guarded(['id', 'created_at', 'updated_at'])]
#[ObservedBy([PurchaseOrderObserver::class])]
final class PurchaseOrder extends Model
{
    use HasFactory;

    /** @return HasMany<PurchaseOrderLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class);
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'date' => 'datetime',
            'status' => PurchaseOrderStatus::class,
        ];
    }

    /** @return Attribute<CompoundPrice, never> */
    protected function total(): Attribute
    {
        return Attribute::get(
            fn () => $this->lines->reduce(
                static fn (CompoundPrice $total, PurchaseOrderLine $line): CompoundPrice => $total->add($line->compoundPrice),
                CompoundPrice::empty(),
            ),
        );
    }
}
```

> `#[Guarded]` matching `Invoice` and `BookkeepingRecord` — `image_path` is mass-assignable (not in the guarded list). `#[ObservedBy]` registers `PurchaseOrderObserver` for file cleanup on delete. `total` accessor sums line `compoundPrice` values into a `CompoundPrice`, same pattern as `Invoice::total()`. The `status` cast uses the `PurchaseOrderStatus` enum.

### Step 3.2 — Create `PurchaseOrderLine` model

```bash
./Taskfile artisan make:model PurchaseOrderLine --no-interaction
```

File: `app/Models/PurchaseOrderLine.php`

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Invoices\CompoundPrice;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

#[Fillable(['description', 'price', 'price_vat', 'cost_center_id'])]
final class PurchaseOrderLine extends Model
{
    use HasFactory;

    /** @return BelongsTo<PurchaseOrder, $this> */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /** @return BelongsTo<CostCenter, $this> */
    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'price' => 'decimal:3',
            'price_vat' => 'decimal:3',
        ];
    }

    /** @return Attribute<CompoundPrice, never> */
    protected function compoundPrice(): Attribute
    {
        return Attribute::get(
            static fn ($_value, array $attributes) => new CompoundPrice(
                (float) ($attributes['price'] ?? 0.0),
                (float) ($attributes['price_vat'] ?? 0.0),
            ),
        );
    }
}
```

> `#[Fillable]` matching `InvoiceLine`. `compoundPrice` accessor returns `CompoundPrice(price, price_vat)` — no quantity multiplication since the line stores totals directly. The `costCenter()` relation matches `InvoiceLine::costCenter()`.

### Step 3.3 — Add `purchaseOrderLines` relation to `CostCenter` model

File: `app/Models/CostCenter.php` — add method:

```php
/** @return HasMany<PurchaseOrderLine, $this> */
public function purchaseOrderLines(): HasMany
{
    return $this->hasMany(PurchaseOrderLine::class);
}
```

> Matches the existing `invoiceLines()` relation. Useful for reporting and potential future queries.

### Step 3.4 — Create `PurchaseOrderObserver`

```bash
./Taskfile artisan make:observer PurchaseOrderObserver --model=PurchaseOrder --no-interaction
```

File: `app/Observers/PurchaseOrderObserver.php`

```php
<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\Storage;

final class PurchaseOrderObserver
{
    public function deleted(PurchaseOrder $purchaseOrder): void
    {
        if ($purchaseOrder->image_path !== null) {
            Storage::disk('local')->delete($purchaseOrder->image_path);
        }
    }
}
```

> Deletes the image file from the `local` disk when a purchase order is deleted. This prevents orphaned files. Registered via `#[ObservedBy]` on the `PurchaseOrder` model (see Step 3.1). Filament's `FileUpload` already handles deleting the old file when a new one is uploaded or when the field is cleared — this observer only handles the model deletion case.

### Step 3.5 — Create `PurchaseOrderFactory`

```bash
./Taskfile artisan make:factory PurchaseOrderFactory --model=PurchaseOrder --no-interaction
```

File: `database/factories/PurchaseOrderFactory.php`

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\PurchaseOrders\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/** @extends Factory<PurchaseOrder> */
final class PurchaseOrderFactory extends Factory
{
    #[Override]
    public function definition(): array
    {
        return [
            'description' => fake()->sentence(),
            'creditor_name' => fake()->company(),
            'date' => fake()->dateTimeBetween('-2 years', 'now'),
            'status' => fake()->randomElement(PurchaseOrderStatus::cases()),
        ];
    }

    public function open(): self
    {
        return $this->state(['status' => PurchaseOrderStatus::Open]);
    }

    public function pending(): self
    {
        return $this->state(['status' => PurchaseOrderStatus::Pending]);
    }

    public function paid(): self
    {
        return $this->state(['status' => PurchaseOrderStatus::Paid]);
    }

    public function withLines(?int $count = null): self
    {
        $count ??= fake()->numberBetween(1, 5);

        return $this->has(PurchaseOrderLine::factory()->count($count), 'lines');
    }
}
```

> Helper states (`open`, `pending`, `paid`) for test readability. `withLines()` mirrors `InvoiceFactory::withLines()`.

### Step 3.6 — Create `PurchaseOrderLineFactory`

```bash
./Taskfile artisan make:factory PurchaseOrderLineFactory --model=PurchaseOrderLine --no-interaction
```

File: `database/factories/PurchaseOrderLineFactory.php`

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CostCenter;
use App\Models\PurchaseOrderLine;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/** @extends Factory<PurchaseOrderLine> */
final class PurchaseOrderLineFactory extends Factory
{
    #[Override]
    public function definition(): array
    {
        $price = fake()->randomFloat(2, 5, 500);

        return [
            'description' => fake()->sentence(),
            'price' => $price,
            'price_vat' => $price * 0.21,
            'cost_center_id' => CostCenter::factory(),
        ];
    }
}
```

> Mirrors `InvoiceLineFactory`. `price_vat` defaults to 21% but tests can override it to test different VAT rates.

---

## Phase 4: Infrastructure Layer

### Step 4.1 — Create `PurchaseOrderRepositoryDb`

File: `app/Infrastructure/PurchaseOrders/PurchaseOrderRepositoryDb.php`

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\PurchaseOrders;

use App\Domain\PurchaseOrders\PurchaseOrderId;
use App\Domain\PurchaseOrders\PurchaseOrderRepository;
use App\Domain\PurchaseOrders\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use Override;

final class PurchaseOrderRepositoryDb implements PurchaseOrderRepository
{
    #[Override]
    public function markAsPending(PurchaseOrderId $id): void
    {
        PurchaseOrder::query()
            ->where('id', $id->value)
            ->update(['status' => PurchaseOrderStatus::Pending]);
    }

    #[Override]
    public function markAsPaid(PurchaseOrderId $id): void
    {
        PurchaseOrder::query()
            ->where('id', $id->value)
            ->update(['status' => PurchaseOrderStatus::Paid]);
    }
}
```

> Simple status updates matching `InvoiceBatchRepositoryDb::markInvoicesAsPending()` pattern (bulk update on query).

---

## Phase 5: Bookkeeping Integration

### Step 5.1 — Add `createForPurchaseOrder` to `BookkeepingRecordRepository` interface

File: `app/Domain/Bookkeeping/BookkeepingRecordRepository.php` — add method:

```php
use App\Domain\PurchaseOrders\PurchaseOrderId;

public function createForPurchaseOrder(PurchaseOrderId $id): void;
```

> The full interface becomes:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Bookkeeping;

use App\Domain\Invoices\InvoiceBatchId;
use App\Domain\PurchaseOrders\PurchaseOrderId;
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
     * Create bookkeeping records for a single purchase order.
     * Records are created per cost center, summing line amounts.
     * Idempotent: skips if records already exist for this purchase order.
     */
    public function createForPurchaseOrder(PurchaseOrderId $id): void;

    /**
     * @return list<CostCenterYearResult>
     */
    public function getResultsForYear(int $year): array;
}
```

### Step 5.2 — Implement `createForPurchaseOrder` in `BookkeepingRecordDbRepository`

File: `app/Infrastructure/Bookkeeping/BookkeepingRecordDbRepository.php` — add method:

```php
use App\Domain\PurchaseOrders\PurchaseOrderId;
use App\Domain\PurchaseOrders\PurchaseOrderStatus;
use App\Models\PurchaseOrder;

#[Override]
public function createForPurchaseOrder(PurchaseOrderId $id): void
{
    $now = now();
    BookkeepingRecord::query()->insertUsing(
        ['year', 'cost_center_id', 'amount_price', 'amount_vat', 'description', 'reference_type', 'reference_id', 'created_at', 'updated_at'],
        PurchaseOrder::query()
            ->where('purchase_orders.id', $id->value)
            ->whereIn('purchase_orders.status', [PurchaseOrderStatus::Pending, PurchaseOrderStatus::Paid])
            ->whereNotExists(static function ($query): void {
                $query
                    ->from('bookkeeping_records')
                    ->whereColumn('bookkeeping_records.reference_id', 'purchase_orders.id')
                    ->where('bookkeeping_records.reference_type', PurchaseOrder::class);
            })
            ->joinRelationship('lines')
            ->groupBy(
                'purchase_orders.id',
                'purchase_orders.description',
                'purchase_orders.date',
                'purchase_order_lines.cost_center_id',
            )
            ->select(
                DB::connection()->getConfig()['driver'] === 'pgsql' ?
                    DB::raw('EXTRACT(YEAR FROM purchase_orders.date) AS year') :
                    DB::raw('STRFTIME(\'%Y\', purchase_orders.date)'),
                'purchase_order_lines.cost_center_id',
                DB::raw('-SUM(purchase_order_lines.price)'),
                DB::raw('-SUM(purchase_order_lines.price_vat)'),
                DB::raw("CONCAT('Purchase order ', purchase_orders.description)"),
                DB::raw("'" . PurchaseOrder::class . "'"),
                'purchase_orders.id',
                $now,
                $now,
            ),
    );
}
```

> This mirrors `createForBatch()` exactly in structure:
> - `insertUsing` to insert bookkeeping records from a subquery.
> - `whereIn('status', [Pending, Paid])` — the method is called after both `markAsPending` and `markAsPaid`. This filter is a safety check; the status is already set by the time this runs.
> - `whereNotExists` — idempotent: skips POs that already have bookkeeping records with `reference_type = PurchaseOrder::class`.
> - `joinRelationship('lines')` — joins `purchase_order_lines` via the PowerJoins package.
> - `groupBy` — groups by PO + cost center, so one bookkeeping record per cost center per PO.
> - `select` — year from `purchase_orders.date`, cost center, summed `price` and `price_vat`, description, reference type/id, timestamps.
> - The `reference` morph points to `PurchaseOrder::class` + `purchase_orders.id`, so the "Go to source" action in the BookkeepingRecords table can link back to the PO.

---

## Phase 6: Authorization

### Step 6.1 — Add Purchase Orders permissions to `ResourcePermission` enum

File: `app/Domain/Authorization/ResourcePermission.php` — add after the Bookkeeping Records section (line 148):

```php
// Purchase Orders
case ViewAnyPurchaseOrders = 'view_any_purchase_orders';
case ViewPurchaseOrders = 'view_purchase_orders';
case CreatePurchaseOrders = 'create_purchase_orders';
case UpdatePurchaseOrders = 'update_purchase_orders';
case DeletePurchaseOrders = 'delete_purchase_orders';
case DeleteAnyPurchaseOrders = 'delete_any_purchase_orders';
```

> Same CRUD pattern as all other resources. The `RolePermissionSeeder::run()` loop `foreach (ResourcePermission::cases() as $permission)` will auto-create these permissions.

### Step 6.2 — Create `PurchaseOrderPolicy`

```bash
./Taskfile artisan make:policy PurchaseOrderPolicy --model=PurchaseOrder --no-interaction
```

File: `app/Policies/PurchaseOrderPolicy.php`

```php
<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\PurchaseOrders\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Webmozart\Assert\Assert;

final class PurchaseOrderPolicy extends ResourcePolicy
{
    protected static function permissionPrefix(): string
    {
        return 'purchase_orders';
    }

    public function update(User $user, Model $purchaseOrder): bool
    {
        Assert::isInstanceOf($purchaseOrder, PurchaseOrder::class);
        return $user->can('update_purchase_orders') && $purchaseOrder->status === PurchaseOrderStatus::Open;
    }

    public function delete(User $user, Model $purchaseOrder): bool
    {
        Assert::isInstanceOf($purchaseOrder, PurchaseOrder::class);
        return $user->can('delete_purchase_orders') && $purchaseOrder->status === PurchaseOrderStatus::Open;
    }
}
```

> Mirrors `InvoicePolicy` exactly — `update` and `delete` require `status === Open`. Other methods (`viewAny`, `view`, `create`, `deleteAny`) inherit from `ResourcePolicy`.

### Step 6.3 — Register `PurchaseOrderPolicy`

File: `app/Providers/AppServiceProvider.php` (or wherever policies are registered — check existing pattern)

> **Check**: Verify how policies are registered. If auto-discovered by Laravel (model → policy convention), no registration needed. The existing policies (e.g., `InvoicePolicy`) follow the `App\Models\Invoice` → `App\Policies\InvoicePolicy` convention, so `PurchaseOrder` → `PurchaseOrderPolicy` should be auto-discovered. Verify in `app/Providers/AppServiceProvider.php` or config.

### Step 6.4 — Grant permissions to `FinancialAdministration` in seeder

File: `database/seeders/RolePermissionSeeder.php` — in `seedFinancialAdministration()` (line 70), add:

```php
$this->allPermissionsFor('purchase_orders'),
```

> Add this line to the `$permissions = array_merge(...)` call in `seedFinancialAdministration()`. This grants all CRUD permissions for purchase orders to the FinancialAdministration role only. No other role gets these permissions, matching the user's requirement.

---

## Phase 7: Filament Resource

### Step 7.1 — Create `PurchaseOrderStatusLabels`

File: `app/Filament/Admin/Labels/PurchaseOrderStatusLabels.php`

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Labels;

use App\Domain\PurchaseOrders\PurchaseOrderStatus;

final class PurchaseOrderStatusLabels
{
    public static function options(): array
    {
        return [
            PurchaseOrderStatus::Open->value => __('labels.purchase_order_status.open'),
            PurchaseOrderStatus::Pending->value => __('labels.purchase_order_status.pending'),
            PurchaseOrderStatus::Paid->value => __('labels.purchase_order_status.paid'),
        ];
    }
}
```

> Mirrors `InvoiceStatusLabels`.

### Step 7.2 — Create `PurchaseOrderResource`

```bash
./Taskfile artisan make:filament-resource PurchaseOrder --no-interaction
```

File: `app/Filament/Admin/Resources/PurchaseOrders/PurchaseOrderResource.php`

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PurchaseOrders;

use App\Filament\Admin\Navigation\NavigationGroup;
use App\Filament\Admin\Resources\PurchaseOrders\Pages\CreatePurchaseOrder;
use App\Filament\Admin\Resources\PurchaseOrders\Pages\EditPurchaseOrder;
use App\Filament\Admin\Resources\PurchaseOrders\Pages\ListPurchaseOrders;
use App\Filament\Admin\Resources\PurchaseOrders\Pages\ViewPurchaseOrder;
use App\Filament\Admin\Resources\PurchaseOrders\Schemas\PurchaseOrderForm;
use App\Filament\Admin\Resources\PurchaseOrders\Tables\PurchaseOrdersTable;
use App\Models\PurchaseOrder;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Override;
use UnitEnum;

final class PurchaseOrderResource extends Resource
{
    protected static ?string $model = PurchaseOrder::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ShoppingCart;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Bookkeeping;

    protected static ?string $recordTitleAttribute = 'description';

    #[Override]
    public static function form(Schema $schema): Schema
    {
        return PurchaseOrderForm::configure($schema);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return PurchaseOrdersTable::configure($table);
    }

    #[Override]
    public static function getPluralLabel(): string
    {
        return __('labels.purchase_orders');
    }

    #[Override]
    public static function getLabel(): string
    {
        return __('labels.purchase_order');
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ListPurchaseOrders::route('/'),
            'create' => CreatePurchaseOrder::route('/create'),
            'edit' => EditPurchaseOrder::route('/{record}/edit'),
            'view' => ViewPurchaseOrder::route('/{record}'),
        ];
    }
}
```

> Structure mirrors `InvoiceResource` and `CostCenterResource`. Navigation group is `Bookkeeping`. Icon `ShoppingCart` is appropriate for purchase orders.

### Step 7.3 — Create `PurchaseOrderForm` schema

File: `app/Filament/Admin/Resources/PurchaseOrders/Schemas/PurchaseOrderForm.php`

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PurchaseOrders\Schemas;

use App\Domain\PurchaseOrders\PurchaseOrderStatus;
use App\Filament\Admin\Labels\PurchaseOrderStatusLabels;
use App\Formatters\PriceFormatter;
use App\Models\CostCenter;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class PurchaseOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('labels.purchase_order_information'))
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextInput::make('creditor_name')
                            ->label(__('labels.creditor_name'))
                            ->required(),
                        DatePicker::make('date')
                            ->label(__('labels.date'))
                            ->native(false)
                            ->format('d-m-Y')
                            ->required(),
                        TextInput::make('description')
                            ->label(__('labels.description'))
                            ->columnSpanFull()
                            ->required(),
                        Select::make('status')
                            ->label(__('labels.status'))
                            ->options(PurchaseOrderStatusLabels::options())
                            ->disabled(),
                        FileUpload::make('image_path')
                            ->label(__('labels.image'))
                            ->image()
                            ->imagePreviewHeight('250')
                            ->directory('purchase-orders')
                            ->disk('local')
                            ->visibility('private')
                            ->previewable(true)
                            ->columnSpanFull(),
                    ]),
                Section::make(__('labels.purchase_order_lines'))
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('lines')
                            ->hiddenLabel()
                            ->relationship()
                            ->collapsed()
                            ->itemLabel(static fn (array $state) => $state['description'] === ''
                                ? '(leeg)'
                                : sprintf('%s %s', $state['description'], PriceFormatter::format((float) $state['price'])))
                            ->columns(2)
                            ->schema([
                                TextInput::make('description')
                                    ->label(__('labels.description'))
                                    ->columnSpanFull()
                                    ->required(),
                                TextInput::make('price')
                                    ->label(__('labels.price'))
                                    ->prefix('€')
                                    ->numeric()
                                    ->required(),
                                TextInput::make('price_vat')
                                    ->label(__('labels.price_vat'))
                                    ->prefix('€')
                                    ->numeric()
                                    ->required(),
                                Select::make('cost_center_id')
                                    ->label(__('labels.cost_center'))
                                    ->options(static fn () => CostCenter::query()->orderBy('number')->pluck('title', 'id'))
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                            ]),
                    ]),
            ]);
    }
}
```

> Key differences from `InvoiceForm`:
> - No member/recipient section — purchase orders have `creditor_name` instead.
> - `price_vat` is a manual input field (not auto-calculated like invoice `vat`).
> - No `quantity` field — the line stores total amounts directly.
> - `status` is disabled (managed via actions, not direct editing).
> - The repeater does not need `mutateRelationshipDataBeforeSaveUsing` since `price_vat` is entered directly.
> - `FileUpload::make('image_path')` — stores the file path in the `image_path` column. `->image()` restricts to image MIME types (security best practice per Filament docs). `->directory('purchase-orders')` stores files under `storage/app/private/purchase-orders/`. `->disk('local')` + `->visibility('private')` keeps files private (Filament generates signed URLs for preview). `->imagePreviewHeight('250')` shows a preview thumbnail in the form. `->columnSpanFull()` gives the upload area full width.

### Step 7.4 — Create `PurchaseOrdersTable`

File: `app/Filament/Admin/Resources/PurchaseOrders/Tables/PurchaseOrdersTable.php`

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PurchaseOrders\Tables;

use App\Domain\Invoices\CompoundPrice;
use App\Domain\PurchaseOrders\PurchaseOrderStatus;
use App\Filament\Admin\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Filament\Admin\Utils\ViewOrEdit;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class PurchaseOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_path')
                    ->label(__('labels.image'))
                    ->disk('local')
                    ->circular()
                    ->size(40)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('creditor_name')
                    ->label(__('labels.creditor_name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('description')
                    ->label(__('labels.description'))
                    ->searchable()
                    ->limit(50),
                TextColumn::make('date')
                    ->label(__('labels.date'))
                    ->sortable()
                    ->date(),
                TextColumn::make('status')
                    ->label(__('labels.status'))
                    ->formatStateUsing(static fn (PurchaseOrderStatus $state) => __('labels.purchase_order_status.' . $state->value))
                    ->sortable(),
                TextColumn::make('total')
                    ->label(__('labels.total'))
                    ->formatStateUsing(static fn (CompoundPrice $state) => (string) $state)
                    ->alignEnd(),
                TextColumn::make('created_at')
                    ->label(__('labels.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label(__('labels.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordUrl(ViewOrEdit::route(PurchaseOrderResource::class))
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
```

> Mirrors `InvoicesTable` with the addition of `ImageColumn::make('image_path')`. `->disk('local')` matches the upload disk for signed URL generation. `->circular()` + `->size(40)` shows a small thumbnail. Hidden by default to keep the table compact — users can toggle it visible. `total` uses the `CompoundPrice` accessor from the `PurchaseOrder::total()` attribute. `status` formats via the `purchase_order_status` labels.

### Step 7.5 — Create Pages

#### `ListPurchaseOrders`

File: `app/Filament/Admin/Resources/PurchaseOrders/Pages/ListPurchaseOrders.php`

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PurchaseOrders\Pages;

use App\Domain\PurchaseOrders\PurchaseOrderStatus;
use App\Filament\Admin\Resources\PurchaseOrders\PurchaseOrderResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs;
use Illuminate\Database\Eloquent\Builder;
use Override;

final class ListPurchaseOrders extends ListRecords
{
    protected static string $resource = PurchaseOrderResource::class;

    #[Override]
    public function getTabs(): array
    {
        return [
            'all' => Tabs\Tab::make(__('labels.all')),
            'open' => Tabs\Tab::make(__('labels.purchase_order_status.open'))
                ->modifyQueryUsing(
                    static fn (Builder $query) => $query->where('status', PurchaseOrderStatus::Open),
                ),
            'pending' => Tabs\Tab::make(__('labels.purchase_order_status.pending'))
                ->modifyQueryUsing(
                    static fn (Builder $query) => $query->where('status', PurchaseOrderStatus::Pending),
                ),
            'paid' => Tabs\Tab::make(__('labels.purchase_order_status.paid'))
                ->modifyQueryUsing(
                    static fn (Builder $query) => $query->where('status', PurchaseOrderStatus::Paid),
                ),
        ];
    }

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
```

> Mirrors `ListInvoices` with tabs per status (without `declined`).

#### `CreatePurchaseOrder`

File: `app/Filament/Admin/Resources/PurchaseOrders/Pages/CreatePurchaseOrder.php`

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PurchaseOrders\Pages;

use App\Domain\PurchaseOrders\PurchaseOrderStatus;
use App\Filament\Admin\Resources\PurchaseOrders\PurchaseOrderResource;
use Carbon\CarbonImmutable;
use Filament\Resources\Pages\CreateRecord;
use Override;

final class CreatePurchaseOrder extends CreateRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    #[Override]
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['date'] ??= CarbonImmutable::now();
        $data['status'] = PurchaseOrderStatus::Open;

        return $data;
    }

    protected function afterFill(): void
    {
        $this->data['date'] = CarbonImmutable::now();
        $this->data['status'] = PurchaseOrderStatus::Open;
    }

    #[Override]
    protected function getCreatedNotificationTitle(): string
    {
        return __('notifications.purchase_order_created');
    }
}
```

> Mirrors `CreateInvoice`. Sets `date` to today and `status` to `Open` on creation.

#### `EditPurchaseOrder`

File: `app/Filament/Admin/Resources/PurchaseOrders/Pages/EditPurchaseOrder.php`

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PurchaseOrders\Pages;

use App\Domain\PurchaseOrders\PurchaseOrderId;
use App\Domain\PurchaseOrders\PurchaseOrderService;
use App\Domain\PurchaseOrders\PurchaseOrderStatus;
use App\Filament\Admin\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Override;

final class EditPurchaseOrder extends EditRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            Action::make('markAsPending')
                ->label(__('labels.mark_as_pending'))
                ->icon('heroicon-m-clock')
                ->color('warning')
                ->requiresConfirmation()
                ->visible(static fn (PurchaseOrder $record): bool => $record->status === PurchaseOrderStatus::Open)
                ->action(static function (PurchaseOrder $record, PurchaseOrderService $service): void {
                    $service->markAsPending(PurchaseOrderId::create($record->id));
                })
                ->successNotificationTitle(__('notifications.purchase_order_marked_pending')),

            Action::make('markAsPaid')
                ->label(__('labels.mark_as_paid'))
                ->icon('heroicon-m-banknotes')
                ->color('success')
                ->requiresConfirmation()
                ->visible(static fn (PurchaseOrder $record): bool => $record->status === PurchaseOrderStatus::Pending)
                ->action(static function (PurchaseOrder $record, PurchaseOrderService $service): void {
                    $service->markAsPaid(PurchaseOrderId::create($record->id));
                })
                ->successNotificationTitle(__('notifications.purchase_order_marked_paid')),

            DeleteAction::make()
                ->visible(static fn (PurchaseOrder $record): bool => $record->status === PurchaseOrderStatus::Open),
        ];
    }
}
```

> Status transition actions follow the `EditInvoiceBatch` action pattern:
> - `markAsPending`: visible when `Open`, calls `PurchaseOrderService::markAsPending()`.
> - `markAsPaid`: visible when `Pending`, calls `PurchaseOrderService::markAsPaid()`.
> - `DeleteAction`: visible only when `Open` (policy enforces this, but the `visible` callback provides UX feedback).
> - Both actions inject `PurchaseOrderService` via the action callback parameter.

#### `ViewPurchaseOrder`

File: `app/Filament/Admin/Resources/PurchaseOrders/Pages/ViewPurchaseOrder.php`

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PurchaseOrders\Pages;

use App\Filament\Admin\Resources\PurchaseOrders\PurchaseOrderResource;
use Filament\Resources\Pages\ViewRecord;

final class ViewPurchaseOrder extends ViewRecord
{
    protected static string $resource = PurchaseOrderResource::class;
}
```

> Mirrors `ViewInvoice`. The view page is used when the user cannot edit (status is not Open).

### Step 7.6 — Update `BookkeepingRecordsTable` "Go to source" action

File: `app/Filament/Admin/Resources/BookkeepingRecords/Tables/BookkeepingRecordsTable.php`

Add `PurchaseOrder` to the `match` in the `related` action URL (line 68):

```php
use App\Filament\Admin\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Models\PurchaseOrder;

// In the match expression:
->url(static fn (BookkeepingRecord $record): string => match (get_class($record->reference)) {
    Invoice::class => ViewOrEdit::routeFor(InvoiceResource::class, $record->reference),
    PurchaseOrder::class => ViewOrEdit::routeFor(PurchaseOrderResource::class, $record->reference),
    default => '',
})
```

> This allows clicking "Go to source" on a bookkeeping record to navigate to the originating purchase order, just as it currently does for invoices.

---

## Phase 8: Labels & Notifications

### Step 8.1 — Add labels to `lang/nl/labels.php`

Add the following entries:

```php
'purchase_order' => 'Inkooporder',
'purchase_orders' => 'Inkooporders',
'purchase_order_information' => 'Inkooporder informatie',
'purchase_order_lines' => 'Inkooporderregels',
'purchase_order_status' => [
    'open' => 'Open',
    'pending' => 'In behandeling',
    'paid' => 'Betaald',
],
'creditor_name' => 'Crediteur',
'date' => 'Datum',
'price_vat' => 'BTW bedrag',
'mark_as_pending' => 'Markeer als in behandeling',
'image' => 'Afbeelding',
```

> `purchase_order_status` mirrors `invoice_status` but without `declined`. `creditor_name` = "Crediteur" (Dutch for creditor/supplier). `date` = "Datum" (generic date label, reusable).

### Step 8.2 — Add notifications to `lang/nl/notifications.php`

Add the following entries:

```php
'purchase_order_created' => 'Inkooporder succesvol aangemaakt',
'purchase_order_marked_pending' => 'Inkooporder gemarkeerd als in behandeling',
'purchase_order_marked_paid' => 'Inkooporder gemarkeerd als betaald',
```

---

## Phase 9: Tests

### Step 9.1 — Unit test: `PurchaseOrderServiceTest`

File: `tests/Unit/Domain/PurchaseOrders/PurchaseOrderServiceTest.php`

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\PurchaseOrders;

use App\Domain\Bookkeeping\BookkeepingRecordRepository;
use App\Domain\PurchaseOrders\PurchaseOrderId;
use App\Domain\PurchaseOrders\PurchaseOrderRepository;
use App\Domain\PurchaseOrders\PurchaseOrderServiceImpl;
use Override;
use Tests\Unit\Domain\Bookkeeping\BookkeepingRecordRepositoryExpectation;
use Tests\UnitTestCase;

final class PurchaseOrderServiceTest extends UnitTestCase
{
    private PurchaseOrderRepositoryExpectation $repo;
    private BookkeepingRecordRepositoryExpectation $bookkeepingRepo;
    private PurchaseOrderServiceImpl $service;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->repo = PurchaseOrderRepositoryExpectation::create();
        $this->bookkeepingRepo = BookkeepingRecordRepositoryExpectation::create();

        $this->service = new PurchaseOrderServiceImpl(
            $this->repo->mock,
            $this->bookkeepingRepo->mock,
        );
    }

    public function test_mark_as_pending_updates_status_and_creates_bookkeeping_records(): void
    {
        $id = PurchaseOrderId::create(1);

        $this->repo->expectsMarkAsPending($id);
        $this->bookkeepingRepo->expectsCreateForPurchaseOrder($id);

        $this->service->markAsPending($id);
    }

    public function test_mark_as_paid_updates_status_and_creates_bookkeeping_records(): void
    {
        $id = PurchaseOrderId::create(2);

        $this->repo->expectsMarkAsPaid($id);
        $this->bookkeepingRepo->expectsCreateForPurchaseOrder($id);

        $this->service->markAsPaid($id);
    }
}
```

### Step 9.2 — Unit test: `PurchaseOrderRepositoryExpectation`

File: `tests/Unit/Domain/PurchaseOrders/PurchaseOrderRepositoryExpectation.php`

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\PurchaseOrders;

use App\Domain\PurchaseOrders\PurchaseOrderId;
use App\Domain\PurchaseOrders\PurchaseOrderRepository;
use Mockery;
use Mockery\MockInterface;

use function PHPUnit\Framework\equalTo;

final readonly class PurchaseOrderRepositoryExpectation
{
    private function __construct(
        public MockInterface&PurchaseOrderRepository $mock,
    ) {}

    public static function create(): self
    {
        return new self(Mockery::mock(PurchaseOrderRepository::class));
    }

    public function expectsMarkAsPending(PurchaseOrderId $id): void
    {
        $this->mock
            ->expects('markAsPending')
            ->with(equalTo($id));
    }

    public function expectsMarkAsPaid(PurchaseOrderId $id): void
    {
        $this->mock
            ->expects('markAsPaid')
            ->with(equalTo($id));
    }
}
```

> Mirrors `InvoiceBatchRepositoryExpectation` and `BookkeepingRecordRepositoryExpectation`.

### Step 9.3 — Update `BookkeepingRecordRepositoryExpectation`

File: `tests/Unit/Domain/Bookkeeping/BookkeepingRecordRepositoryExpectation.php` — add method:

```php
use App\Domain\PurchaseOrders\PurchaseOrderId;

public function expectsCreateForPurchaseOrder(PurchaseOrderId $id): void
{
    $this->mock
        ->expects('createForPurchaseOrder')
        ->with(equalTo($id));
}
```

### Step 9.4 — Feature test: `BookkeepingRecordDbRepositoryTest` — add `createForPurchaseOrder` tests

File: `tests/Feature/Infrastructure/Bookkeeping/BookkeepingRecordDbRepositoryTest.php` — add tests:

```php
public function test_create_for_purchase_order_creates_records_per_cost_center(): void
{
    $costCenterA = CostCenter::factory()->create();
    $costCenterB = CostCenter::factory()->create();

    $po = PurchaseOrder::factory()
        ->has(PurchaseOrderLine::factory()->state(['cost_center_id' => $costCenterA->id, 'price' => 100, 'price_vat' => 21]), 'lines')
        ->has(PurchaseOrderLine::factory()->state(['cost_center_id' => $costCenterB->id, 'price' => 50, 'price_vat' => 10.5]), 'lines')
        ->create([
            'date' => '2026-06-15',
            'status' => 'pending',
        ]);

    $this->repository->createForPurchaseOrder(PurchaseOrderId::create($po->id));

    $records = BookkeepingRecord::query()->where('reference_type', PurchaseOrder::class)->get();

    static::assertCount(2, $records);
    static::assertTrue($records->contains(static fn ($r) => $r->cost_center_id === $costCenterA->id && (float) $r->amount_price === 100.0 && (float) $r->amount_vat === 21.0));
    static::assertTrue($records->contains(static fn ($r) => $r->cost_center_id === $costCenterB->id && (float) $r->amount_price === 50.0 && (float) $r->amount_vat === 10.5));
    static::assertSame(2026, $records->first()->year);
}

public function test_create_for_purchase_order_skips_already_existing_records(): void
{
    $costCenter = CostCenter::factory()->create();

    $po = PurchaseOrder::factory()
        ->has(PurchaseOrderLine::factory()->state(['cost_center_id' => $costCenter->id, 'price' => 100, 'price_vat' => 21]), 'lines')
        ->create([
            'date' => '2026-06-15',
            'status' => 'paid',
        ]);

    BookkeepingRecord::factory()->create([
        'reference_type' => PurchaseOrder::class,
        'reference_id' => $po->id,
        'cost_center_id' => $costCenter->id,
        'year' => 2026,
        'amount_price' => 100,
        'amount_vat' => 21,
    ]);

    $this->repository->createForPurchaseOrder(PurchaseOrderId::create($po->id));

    $records = BookkeepingRecord::query()->where('reference_type', PurchaseOrder::class)->get();

    static::assertCount(1, $records);
    static::assertSame(100.0, (float) $records->first()->amount_price);
}

public function test_create_for_purchase_order_open_status_creates_nothing(): void
{
    $costCenter = CostCenter::factory()->create();

    $po = PurchaseOrder::factory()
        ->has(PurchaseOrderLine::factory()->state(['cost_center_id' => $costCenter->id, 'price' => 100, 'price_vat' => 21]), 'lines')
        ->create([
            'status' => 'open',
        ]);

    $this->repository->createForPurchaseOrder(PurchaseOrderId::create($po->id));

    static::assertSame(0, BookkeepingRecord::query()->where('reference_type', PurchaseOrder::class)->count());
}
```

> Add `use App\Domain\PurchaseOrders\PurchaseOrderId;`, `use App\Models\PurchaseOrder;`, `use App\Models\PurchaseOrderLine;` to the test file imports. These mirror the existing `test_create_for_batch_*` tests.

### Step 9.5 — Feature test: `PurchaseOrderResourceTest`

File: `tests/Feature/Filament/Admin/Resources/PurchaseOrderResourceTest.php`

Test the Filament resource CRUD and status transitions:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Admin\Resources;

use App\Domain\Bookkeeping\BookkeepingRecordRepository;
use App\Domain\PurchaseOrders\PurchaseOrderStatus;
use App\Filament\Admin\Resources\PurchaseOrders\Pages\ListPurchaseOrders;
use App\Models\BookkeepingRecord;
use App\Models\CostCenter;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use Livewire\Livewire;
use Mockery\MockInterface;
use Tests\Concerns\WithAuthorizedUser;
use Tests\FeatureTestCase;

final class PurchaseOrderResourceTest extends FeatureTestCase
{
    use WithAuthorizedUser;

    public function test_financial_administration_can_list_purchase_orders(): void
    {
        $this->withUserHavingRole(RoleName::FinancialAdministration);

        Livewire::test(ListPurchaseOrders::class)
            ->assertSuccessful();
    }

    public function test_can_create_purchase_order_with_lines(): void
    {
        $this->withAuthorizedUser();
        $costCenter = CostCenter::factory()->create();

        Livewire::test(CreatePurchaseOrder::class)
            ->fillForm([
                'creditor_name' => 'Acme Corp',
                'description' => 'Office supplies',
                'date' => '2026-06-26',
                'lines' => [
                    ['description' => 'Paper', 'price' => 100, 'price_vat' => 21, 'cost_center_id' => $costCenter->id],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('purchase_orders', [
            'creditor_name' => 'Acme Corp',
            'description' => 'Office supplies',
            'status' => PurchaseOrderStatus::Open->value,
        ]);
    }

    public function test_mark_as_pending_creates_bookkeeping_records(): void
    {
        $this->withAuthorizedUser();
        $costCenter = CostCenter::factory()->create();

        $po = PurchaseOrder::factory()
            ->has(PurchaseOrderLine::factory()->state(['cost_center_id' => $costCenter->id, 'price' => 100, 'price_vat' => 21]), 'lines')
            ->create(['status' => PurchaseOrderStatus::Open, 'date' => '2026-06-15']);

        Livewire::test(EditPurchaseOrder::class, ['record' => $po->getRouteKey()])
            ->callAction('markAsPending');

        $po->refresh();
        $this->assertSame(PurchaseOrderStatus::Pending, $po->status);

        $this->assertDatabaseHas('bookkeeping_records', [
            'reference_type' => PurchaseOrder::class,
            'reference_id' => $po->id,
            'cost_center_id' => $costCenter->id,
            'year' => 2026,
        ]);
    }

    public function test_mark_as_paid_creates_bookkeeping_records(): void
    {
        $this->withAuthorizedUser();
        $costCenter = CostCenter::factory()->create();

        $po = PurchaseOrder::factory()
            ->has(PurchaseOrderLine::factory()->state(['cost_center_id' => $costCenter->id, 'price' => 100, 'price_vat' => 21]), 'lines')
            ->create(['status' => PurchaseOrderStatus::Pending, 'date' => '2026-06-15']);

        Livewire::test(EditPurchaseOrder::class, ['record' => $po->getRouteKey()])
            ->callAction('markAsPaid');

        $po->refresh();
        $this->assertSame(PurchaseOrderStatus::Paid, $po->status);

        $this->assertDatabaseHas('bookkeeping_records', [
            'reference_type' => PurchaseOrder::class,
            'reference_id' => $po->id,
        ]);
    }

    public function test_cannot_edit_purchase_order_when_not_open(): void
    {
        $this->withAuthorizedUser();
        $po = PurchaseOrder::factory()->create(['status' => PurchaseOrderStatus::Pending]);

        // Policy should deny update
        $this->assertFalse(Gate::allows('update', $po));
    }
}
```

> Add necessary imports (`RoleName`, `Gate`, `CreatePurchaseOrder`, `EditPurchaseOrder`). The `markAsPending`/`markAsPaid` tests verify both the status change and the bookkeeping record creation end-to-end.

### Step 9.6 — Authorization tests

File: `tests/Feature/Authorization/AuthorizationTest.php` — add tests:

```php
public function test_financial_administration_can_view_purchase_orders(): void
{
    $this->withUserHavingRole(RoleName::FinancialAdministration);

    Livewire::test(ListPurchaseOrders::class)
        ->assertSuccessful();
}

public function test_member_administration_cannot_view_purchase_orders(): void
{
    $this->withUserHavingRole(RoleName::MemberAdministration);

    Livewire::test(ListPurchaseOrders::class)
        ->assertForbidden();
}

public function test_activity_administration_cannot_view_purchase_orders(): void
{
    $this->withUserHavingRole(RoleName::ActivityAdministration);

    Livewire::test(ListPurchaseOrders::class)
        ->assertForbidden();
}
```

> Add `use App\Filament\Admin\Resources\PurchaseOrders\Pages\ListPurchaseOrders;` to imports. Verifies that only FinancialAdministration can access purchase orders.

---

## File Summary

### New Files

| File | Purpose |
|------|---------|
| `database/migrations/_____create_purchase_orders_table.php` | PO table migration |
| `database/migrations/____create_purchase_order_lines_table.php` | PO lines table migration |
| `app/Domain/PurchaseOrders/PurchaseOrderStatus.php` | Status enum (open, pending, paid) |
| `app/Domain/PurchaseOrders/PurchaseOrderId.php` | ID value object |
| `app/Domain/PurchaseOrders/PurchaseOrderRepository.php` | Repository interface (#[Autowire]) |
| `app/Domain/PurchaseOrders/PurchaseOrderService.php` | Service interface (#[Autowire]) |
| `app/Domain/PurchaseOrders/PurchaseOrderServiceImpl.php` | Service impl: status + bookkeeping orchestration |
| `app/Infrastructure/PurchaseOrders/PurchaseOrderRepositoryDb.php` | Repository DB impl: status updates |
| `app/Models/PurchaseOrder.php` | Eloquent model with `lines` HasMany, `total` accessor, `#[ObservedBy]` for image cleanup |
| `app/Models/PurchaseOrderLine.php` | Eloquent model with `compoundPrice` accessor |
| `app/Observers/PurchaseOrderObserver.php` | Observer: deletes image file from disk on PO deletion |
| `database/factories/PurchaseOrderFactory.php` | Factory with `open/pending/paid/withLines` states |
| `database/factories/PurchaseOrderLineFactory.php` | Factory for PO lines |
| `app/Policies/PurchaseOrderPolicy.php` | Policy: CRUD + update/delete only when Open |
| `app/Filament/Admin/Labels/PurchaseOrderStatusLabels.php` | Status label options |
| `app/Filament/Admin/Resources/PurchaseOrders/PurchaseOrderResource.php` | Filament resource |
| `app/Filament/Admin/Resources/PurchaseOrders/Schemas/PurchaseOrderForm.php` | Form schema with image upload + repeater |
| `app/Filament/Admin/Resources/PurchaseOrders/Tables/PurchaseOrdersTable.php` | Table columns with image thumbnail |
| `app/Filament/Admin/Resources/PurchaseOrders/Pages/ListPurchaseOrders.php` | List page with status tabs |
| `app/Filament/Admin/Resources/PurchaseOrders/Pages/CreatePurchaseOrder.php` | Create page (sets date + Open) |
| `app/Filament/Admin/Resources/PurchaseOrders/Pages/EditPurchaseOrder.php` | Edit page with markAsPending/markAsPaid actions |
| `app/Filament/Admin/Resources/PurchaseOrders/Pages/ViewPurchaseOrder.php` | View page |
| `tests/Unit/Domain/PurchaseOrders/PurchaseOrderServiceTest.php` | Unit test for service |
| `tests/Unit/Domain/PurchaseOrders/PurchaseOrderRepositoryExpectation.php` | Mockery expectation helper |
| `tests/Feature/Filament/Admin/Resources/PurchaseOrderResourceTest.php` | Feature test for resource |

### Modified Files

| File | Change |
|------|--------|
| `app/Domain/Authorization/ResourcePermission.php` | Add 6 Purchase Orders permission cases |
| `app/Domain/Bookkeeping/BookkeepingRecordRepository.php` | Add `createForPurchaseOrder(PurchaseOrderId)` method |
| `app/Infrastructure/Bookkeeping/BookkeepingRecordDbRepository.php` | Implement `createForPurchaseOrder()` with `insertUsing` |
| `app/Models/CostCenter.php` | Add `purchaseOrderLines()` HasMany relation |
| `database/seeders/RolePermissionSeeder.php` | Add `allPermissionsFor('purchase_orders')` to FinancialAdministration |
| `app/Filament/Admin/Resources/BookkeepingRecords/Tables/BookkeepingRecordsTable.php` | Add PurchaseOrder to "Go to source" match |
| `lang/nl/labels.php` | Add purchase order labels + status labels |
| `lang/nl/notifications.php` | Add purchase order notifications |
| `tests/Unit/Domain/Bookkeeping/BookkeepingRecordRepositoryExpectation.php` | Add `expectsCreateForPurchaseOrder()` |
| `tests/Feature/Infrastructure/Bookkeeping/BookkeepingRecordDbRepositoryTest.php` | Add `createForPurchaseOrder` tests |
| `tests/Feature/Authorization/AuthorizationTest.php` | Add PO authorization tests |

---

## Data Flow

### Create Purchase Order
```
User → CreatePurchaseOrder page
     → mutateFormDataBeforeCreate: sets date=today, status=Open
     → PurchaseOrder saved with lines (via Repeater relationship)
     → Image file (if uploaded) stored on local disk in purchase-orders/ directory
     → image_path column stores the file path
```

### Mark as Pending (Open → Pending)
```
User clicks "Mark as Pending" on EditPurchaseOrder
     → PurchaseOrderService::markAsPending(PurchaseOrderId)
          → PurchaseOrderRepository::markAsPending(id)
               → UPDATE purchase_orders SET status='pending' WHERE id=?
          → BookkeepingRecordRepository::createForPurchaseOrder(id)
               → INSERT INTO bookkeeping_records SELECT ... FROM purchase_orders
                 JOIN purchase_order_lines GROUP BY cost_center
                 WHERE status IN (pending, paid) AND NOT EXISTS (existing record)
     → Notification: "Inkooporder gemarkeerd als in behandeling"
```

### Mark as Paid (Pending → Paid)
```
User clicks "Mark as Paid" on EditPurchaseOrder
     → PurchaseOrderService::markAsPaid(PurchaseOrderId)
          → PurchaseOrderRepository::markAsPaid(id)
               → UPDATE purchase_orders SET status='paid' WHERE id=?
          → BookkeepingRecordRepository::createForPurchaseOrder(id)
               → whereNotExists check: records already exist from pending transition
               → No new records inserted (idempotent)
     → Notification: "Inkooporder gemarkeerd als betaald"
```

### Bookkeeping Records Table → Go to Source
```
BookkeepingRecords table → "Go to source" action
     → match reference_type:
          Invoice::class → InvoiceResource view/edit
          PurchaseOrder::class → PurchaseOrderResource view/edit  [NEW]
```

### Delete Purchase Order
```
User deletes PurchaseOrder (only allowed when status=Open)
     → PurchaseOrderObserver::deleted()
          → Storage::disk('local')->delete(image_path)  — removes image file from disk
     → PurchaseOrder lines cascade-deleted via FK constraint
```

---

## Implementation Order

1. **Migrations** (Phase 1) — run `./Taskfile artisan migrate`
2. **Domain layer** (Phase 2) — status enum, ID, interfaces, service impl
3. **Models, observer & factories** (Phase 3) — run `./Taskfile artisan make:model` / `make:factory` / `make:observer`
4. **Infrastructure** (Phase 4) — repository DB impl
5. **Bookkeeping integration** (Phase 5) — extend interface + impl
6. **Authorization** (Phase 6) — permissions, policy, seeder
7. **Filament resource** (Phase 7) — run `./Taskfile artisan make:filament-resource`, then customize
8. **Labels & notifications** (Phase 8) — add to lang files
9. **Tests** (Phase 9) — write and run tests after each phase

### Verification Commands

```bash
# Run migrations
./Taskfile artisan migrate

# Run unit tests for the service
./Taskfile artisan test --filter=PurchaseOrderServiceTest

# Run bookkeeping repository tests
./Taskfile artisan test --filter=BookkeepingRecordDbRepositoryTest

# Run authorization tests
./Taskfile artisan test --filter=AuthorizationTest

# Run resource feature tests
./Taskfile artisan test --filter=PurchaseOrderResourceTest

# Run all tests
./Taskfile artisan test --compact

# Re-seed permissions (if needed in dev)
./Taskfile artisan db:seed --class=RolePermissionSeeder
```
