# Implementation Plan: BankingTransaction Attach Filtering and Mark-as-Paid

**Date**: 2026-07-17
**TODO Items**:
1. Filter and order attachable invoices/POs by status and relevancy
2. Mark invoices/POs as paid when attaching to a BankingTransaction

---

## Architecture Overview

### Current State

**Filament Layer**:
- `AttachInvoiceAction` calls `BankTransactionRepository::attachInvoice()` directly
- `AttachPurchaseOrderAction` calls `BankTransactionRepository::attachPurchaseOrder()` directly
- No filtering, no service layer, no mark-as-paid on attach

**Domain Layer**:
- `BankTransactionRepository` (interface) - attachInvoice, attachPurchaseOrder, detach*, etc.
- `PurchaseOrderService` (existing) - markAsPending, markAsPaid (marks status + creates bookkeeping)
- `InvoiceBatchService` (existing) - batch-level only, closeBatch (marks Pending + bookkeeping)
- No `InvoiceService` for individual invoice operations
- No `BankTransactionService` for orchestration

### Target State

**Filament Layer**:
- Actions call `BankTransactionService::attachInvoice()` / `attachPurchaseOrder()` instead of repository directly
- Select dropdowns filter by Open/Pending status and order by relevancy

**Domain Layer (new services)**:
- `BankTransactionService` (NEW) - orchestrates mark-as-paid + attach in a transaction
- `InvoiceService` (NEW) - marks individual invoice as paid + creates bookkeeping

---

## Implementation Order

The two TODO items are interdependent since the Filament actions (TODO 1) will be updated to call the new `BankTransactionService` (TODO 2). The recommended implementation order is:

**Phase A**: Domain foundation (TODO 2 infrastructure)
**Phase B**: Filament actions update (both TODO 1 and TODO 2)
**Phase C**: Tests

---

## TODO 1: Status Filtering and Relevancy Ordering

### Files to Modify

#### 1. `app/Models/Invoice.php`

Add two local query scopes:

**(a) `scopeOpenOrPending(Builder $query): void`**
- Add `use Illuminate\Database\Eloquent\Builder;` import
- Add `use App\Domain\Invoices\InvoiceStatus;` import
- Method filters `->whereIn('status', [InvoiceStatus::Open, InvoiceStatus::Pending])`

**(b) `scopeOrderByAmountProximity(Builder $query, float $targetAmount): void`**
- Add `use Illuminate\Support\Facades\DB;` import
- Orders by the absolute difference between the invoice computed total and the target amount
- Uses a correlated subquery for the total since Invoice total is computed from lines:
  ```
  $query->orderByRaw(
      'ABS(COALESCE((SELECT SUM(price * quantity) FROM invoice_lines WHERE invoice_lines.invoice_id = invoices.id), 0) - ?) ASC',
      [$targetAmount],
  );
  ```
- The Invoice total accessor computes `SUM(price * quantity)` per line, so the subquery matches

#### 2. `app/Models/PurchaseOrder.php`

Add two local query scopes:

**(a) `scopeOpenOrPending(Builder $query): void`**
- Add `use Illuminate\Database\Eloquent\Builder;` import
- Add `use App\Domain\PurchaseOrders\PurchaseOrderStatus;` import
- Method filters `->whereIn('status', [PurchaseOrderStatus::Open, PurchaseOrderStatus::Pending])`

**(b) `scopeOrderByRelevancy(Builder $query, float $targetAmount, string $accountNumber): void`**
- Add `use Illuminate\Support\Facades\DB;` import
- Orders by two criteria:
  1. IBAN match first: `CASE WHEN creditor_iban = ? THEN 0 ELSE 1 END ASC`
  2. Amount proximity second within each IBAN group
- PurchaseOrder total accessor uses `$line->compoundPrice` which is just `price` (no quantity), so the subquery uses `SUM(price)` only:
  ```
  $query->orderByRaw(
      'CASE WHEN creditor_iban = ? THEN 0 ELSE 1 END ASC, ABS(COALESCE((SELECT SUM(price) FROM purchase_order_lines WHERE purchase_order_lines.purchase_order_id = purchase_orders.id), 0) - ?) ASC',
      [$accountNumber, $targetAmount],
  );
  ```
- Also add `->orderBy('id', 'asc')` as final tiebreaker for stable ordering

#### 3. `app/Filament/Admin/Resources/BankingTransactions/Actions/AttachInvoiceAction.php`

**Current behavior**:
- Select uses `Invoice::query()->get()` (no filter, no order)
- Action callback receives `mixed $record` (the BankingTransaction) and `BankTransactionRepository $repository`
- Directly calls `$repository->attachInvoice()`

**Changes**:

**(a) Update Select options callback**:
- Apply `->openOrPending()` scope
- Apply `->orderByAmountProximity((float) $record->amount)` scope
- Eager load `lines` and `member` relationships
- Keep same label format: `"#{$invoice->invoice_number} - {$invoice->member->fullName}"`

**(b) Update action callback** (also part of TODO 2):
- Replace `BankTransactionRepository` dependency with `BankTransactionService`
- Call `$service->attachInvoice(BankTransactionId::create((int) $record->id), InvoiceId::create((int) $data['invoice_id']))`

**(c) Imports to add**:
- `use App\Domain\BankTransactions\BankTransactionService;`

#### 4. `app/Filament/Admin/Resources/BankingTransactions/Actions/AttachPurchaseOrderAction.php`

**Current behavior**:
- Select uses `PurchaseOrder::query()->get()` (no filter, no order)
- Action callback receives `RelationManager $livewire` and `BankTransactionRepository $repository`
- Gets owner record via `$livewire->getOwnerRecord()`
- Directly calls `$repository->attachPurchaseOrder()`

**Changes**:

**(a) Update Select options callback**:
- The options callback needs access to the banking transaction record
- Since the action receives `RelationManager $livewire`, capture it with `use ($livewire)`
- Inside callback: `$model = $livewire->getOwnerRecord();`
- Apply `->openOrPending()` scope
- Apply `->orderByRelevancy((float) $model->amount, $model->banking_account_number)` scope
- Eager load `lines` relationship
- Keep same label format (currently just `$po->description`)

**(b) Update action callback** (also part of TODO 2):
- Replace `BankTransactionRepository` dependency with `BankTransactionService`
- Call `$service->attachPurchaseOrder(BankTransactionId::create((int) $model->id), PurchaseOrderId::create((int) $data['purchase_order_id']))`

**(c) Imports to add**:
- `use App\Domain\BankTransactions\BankTransactionService;`

### Edge Cases for TODO 1

| Edge Case | Handling |
|-----------|----------|
| No Open/Pending invoices exist | Select dropdown is empty (acceptable) |
| No Open/Pending purchase orders exist | Select dropdown is empty |
| Invoice/PO total exactly equals BT amount | Ranks highest (diff = 0) |
| Multiple POs with same IBAN + same proximity | Stable by ID as tiebreaker |
| PO with null creditor_iban | CASE WHEN treats NULL != value, ranks lower |
| Invoice with no lines (total = 0) | COALESCE returns 0 |
| Float precision on large amounts | Database decimal arithmetic in subquery |

---

## TODO 2: Mark as Paid When Attaching

### 2.1 New Domain Interface: InvoiceService

#### File to Create: `app/Domain/Invoices/InvoiceService.php`

```php
<?php

declare(strict_types=1);

namespace App\Domain\Invoices;

use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface InvoiceService
{
    /**
     * Mark an invoice as paid and create bookkeeping records.
     * Idempotent: if already paid, skips status update but still ensures bookkeeping exists.
     */
    public function markAsPaid(InvoiceId $id): void;
}
```

**Rationale**: Follows the same pattern as `PurchaseOrderService`. Individual invoice operations are separate from batch operations (`InvoiceBatchService`). This is a new interface, not an addition to `InvoiceBatchService`, because:
- `InvoiceBatchService` handles batch lifecycle (create, close, complete)
- `InvoiceService` handles individual invoice lifecycle (markAsPaid)
- Matches the separation already present with `PurchaseOrderService` vs batch operations

### 2.2 New Domain Implementation: InvoiceServiceImpl

#### File to Create: `app/Domain/Invoices/InvoiceServiceImpl.php`

```php
<?php

declare(strict_types=1);

namespace App\Domain\Invoices;

use App\Domain\Bookkeeping\BookkeepingRecordRepository;
use Override;

final readonly class InvoiceServiceImpl implements InvoiceService
{
    public function __construct(
        private InvoiceRepository $invoiceRepository,
        private BookkeepingRecordRepository $bookkeepingRepository,
    ) {}

    #[Override]
    public function markAsPaid(InvoiceId $id): void
    {
        $this->invoiceRepository->markAsPaid($id);
        $this->bookkeepingRepository->createForInvoice($id);
    }
}
```

**Design notes**:
- Follows the exact same pattern as `PurchaseOrderServiceImpl`:
  1. Update status via repository
  2. Create bookkeeping records (idempotent via `whereNotExists` at DB level)
- Constructor uses `InvoiceRepository` (for status update) and `BookkeepingRecordRepository` (for bookkeeping)
- Both operations happen without explicit DB transaction here; the calling `BankTransactionServiceImpl` wraps everything in a transaction

### 2.3 Add markAsPaid to InvoiceRepository

#### File to Modify: `app/Domain/Invoices/InvoiceRepository.php`

Add method:
```php
/**
 * Mark an individual invoice as paid by updating its status.
 */
public function markAsPaid(InvoiceId $id): void;
```

Add import:
```php
use App\Domain\Invoices\InvoiceId;  // already imported
```

#### File to Modify: `app/Infrastructure/Invoices/InvoiceRepositoryDb.php`

Add implementation of `markAsPaid`:
```php
#[Override]
public function markAsPaid(InvoiceId $id): void
{
    Invoice::query()
        ->where('id', $id->value)
        ->update(['status' => InvoiceStatus::Paid]);
}
```

**Note**: This is a simple status update. The method does NOT guard against marking already-paid invoices - that responsibility lies with the caller (which only calls this for filtering-compatible invoices that are Open or Pending). The `BookkeepingRecordRepository::createForInvoice` is idempotent anyway.

### 2.4 Add createForInvoice to BookkeepingRecordRepository

#### File to Modify: `app/Domain/Bookkeeping/BookkeepingRecordRepository.php`

Add import:
```php
use App\Domain\Invoices\InvoiceId;
```

Add method:
```php
/**
 * Create bookkeeping records for a single invoice.
 * Records are created per cost center, summing line subtotals.
 * Idempotent: skips if records already exist for this invoice.
 */
public function createForInvoice(InvoiceId $id): void;
```

#### File to Modify: `app/Infrastructure/Bookkeeping/BookkeepingRecordDbRepository.php`

Add implementation of `createForInvoice`:

```php
#[Override]
public function createForInvoice(InvoiceId $id): void
{
    $now = now();
    BookkeepingRecord::query()->insertUsing(
        ['year', 'cost_center_id', 'amount_price', 'amount_vat', 'description', 'reference_type', 'reference_id', 'created_at', 'updated_at'],
        Invoice::query()
            ->where('invoices.id', $id->value)
            ->whereNotExists(static function ($query): void {
                $query
                    ->from('bookkeeping_records')
                    ->whereColumn('bookkeeping_records.reference_id', 'invoices.id')
                    ->where('bookkeeping_records.reference_type', Invoice::class);
            })
            ->joinRelationship('lines')
            ->groupBy(
                'invoices.id',
                'invoices.invoice_number',
                'invoice_lines.cost_center_id',
                'invoices.date',
            )
            ->select(
                /* year extraction based on driver (pgsql vs sqlite) */
                DB::connection()->getConfig()['driver'] === 'pgsql'
                    ? DB::raw('EXTRACT(YEAR FROM invoices.date) AS year')
                    : DB::raw("STRFTIME('%Y', invoices.date)"),
                'invoice_lines.cost_center_id',
                DB::raw('SUM(invoice_lines.price * invoice_lines.quantity)'),
                DB::raw('SUM(invoice_lines.vat * invoice_lines.quantity)'),
                DB::raw("CONCAT('Invoice ', invoices.invoice_number)"),
                DB::raw(DB::escape(Invoice::class)),
                'invoices.id',
                DB::raw(DB::escape($now->format('c'))),
                DB::raw(DB::escape($now->format('c'))),
            ),
    );
}
```

**Key differences from `createForBatch`**:
- Filters by single invoice ID (`where('invoices.id', $id->value)`) instead of batch ID
- Does NOT filter by `InvoiceStatus::Pending` - allows creating records regardless of status (since we may be creating records after marking as Paid)
- Uses `invoices.date` for year extraction instead of `invoice_batches.invoice_date`
- Idempotent via `whereNotExists` (same pattern)

### 2.5 New Domain Interface: BankTransactionService

#### File to Create: `app/Domain/BankTransactions/BankTransactionService.php`

```php
<?php

declare(strict_types=1);

namespace App\Domain\BankTransactions;

use App\Domain\Invoices\InvoiceId;
use App\Domain\PurchaseOrders\PurchaseOrderId;
use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface BankTransactionService
{
    /**
     * Mark an invoice as paid and attach it to a banking transaction.
     * This ensures bookkeeping records are created before attachment.
     * Wrapped in a database transaction.
     */
    public function attachInvoice(BankTransactionId $bankTransactionId, InvoiceId $invoiceId): void;

    /**
     * Mark a purchase order as paid and attach it to a banking transaction.
     * This ensures bookkeeping records are created before attachment.
     * Wrapped in a database transaction.
     */
    public function attachPurchaseOrder(BankTransactionId $bankTransactionId, PurchaseOrderId $purchaseOrderId): void;
}
```

**Design note**: Two separate methods (`attachInvoice` / `attachPurchaseOrder`) rather than a single union-type method, because:
- The implementation details differ (different services called)
- Each method clearly documents its specific behavior
- Follows the existing pattern in `BankTransactionRepository` which also has separate methods

### 2.6 New Domain Implementation: BankTransactionServiceImpl

#### File to Create: `app/Domain/BankTransactions/BankTransactionServiceImpl.php`

```php
<?php

declare(strict_types=1);

namespace App\Domain\BankTransactions;

use App\Domain\Invoices\InvoiceId;
use App\Domain\Invoices\InvoiceService;
use App\Domain\PurchaseOrders\PurchaseOrderId;
use App\Domain\PurchaseOrders\PurchaseOrderService;
use Illuminate\Support\Facades\DB;
use Override;

final readonly class BankTransactionServiceImpl implements BankTransactionService
{
    public function __construct(
        private BankTransactionRepository $repository,
        private InvoiceService $invoiceService,
        private PurchaseOrderService $purchaseOrderService,
    ) {}

    #[Override]
    public function attachInvoice(BankTransactionId $bankTransactionId, InvoiceId $invoiceId): void
    {
        DB::transaction(function () use ($bankTransactionId, $invoiceId): void {
            $this->invoiceService->markAsPaid($invoiceId);
            $this->repository->attachInvoice($bankTransactionId, $invoiceId);
        });
    }

    #[Override]
    public function attachPurchaseOrder(BankTransactionId $bankTransactionId, PurchaseOrderId $purchaseOrderId): void
    {
        DB::transaction(function () use ($bankTransactionId, $purchaseOrderId): void {
            $this->purchaseOrderService->markAsPaid($purchaseOrderId);
            $this->repository->attachPurchaseOrder($bankTransactionId, $purchaseOrderId);
        });
    }
}
```

**Design notes**:
- Wraps both operations in `DB::transaction()` for atomicity
- If `markAsPaid` succeeds but `attach*` fails, everything rolls back
- Does NOT check if already paid before calling `markAsPaid`:
  - For PurchaseOrders: `markAsPaid` updates status then creates bookkeeping (idempotent)
  - For Invoices: `markAsPaid` updates status then creates bookkeeping (idempotent)
  - The Filament UI already filters to Open/Pending only, so this is a safety net
  - If somehow called on an already-paid record, the idempotent bookkeeping means no duplicates

**Edge cases handled**:
- **Already paid**: Bookkeeping is idempotent (`whereNotExists` guard); status update is a no-op if already `Paid`
- **Transaction rollback**: If either operation fails, both roll back
- **Concurrent calls**: Database transaction provides isolation

### 2.7 Update Filament Attach Actions (Final)

These changes were partially described in TODO 1. Here is the complete update:

#### File: `app/Filament/Admin/Resources/BankingTransactions/Actions/AttachInvoiceAction.php`

Complete changes:
1. Replace `BankTransactionRepository $repository` with `BankTransactionService $service` in the action callback
2. Call `$service->attachInvoice(...)` instead of `$repository->attachInvoice(...)`
3. Update imports: remove `BankTransactionRepository`, add `BankTransactionService`
4. Update Select options as described in TODO 1.2 (filter + order)

#### File: `app/Filament/Admin/Resources/BankingTransactions/Actions/AttachPurchaseOrderAction.php`

Complete changes:
1. Replace `BankTransactionRepository $repository` with `BankTransactionService $service` in the action callback
2. Call `$service->attachPurchaseOrder(...)` instead of `$repository->attachPurchaseOrder(...)`
3. Update imports: remove `BankTransactionRepository`, add `BankTransactionService`
4. Update Select options as described in TODO 1.2 (filter + order)

---

## Testing Plan

### TODO 1 Tests

#### Test File: `tests/Feature/Filament/BankingTransaction/BankingTransactionResourceTest.php`

**Test methods to add**:

1. `test_attach_invoice_select_only_shows_open_or_pending_invoices()`
   - Create a BankingTransaction
   - Create 3 Invoices: one Open, one Pending, one Paid
   - Mount the ViewBankingTransaction page
   - Assert the attach invoice select options contain only the Open and Pending invoices
   - Assert the Paid invoice is NOT in the options

2. `test_attach_invoice_select_orders_by_amount_proximity()`
   - Create a BankingTransaction with amount 100.00
   - Create 3 Open invoices with lines totaling: 90.00, 110.00, 500.00
   - Mount the ViewBankingTransaction page
   - Assert the invoice options are ordered: 110.00 (diff 10), 90.00 (diff 10), 500.00 (diff 400)
   - For equal diffs, tiebreak by invoice ID

3. `test_attach_purchase_order_select_only_shows_open_or_pending()`
   - Create a BankingTransaction
   - Create POs: one Open, one Pending, one Paid
   - Assert only Open and Pending are in options

4. `test_attach_purchase_order_select_orders_by_iban_match_then_amount_proximity()`
   - Create a BankingTransaction with account `NL00ABCD1234567890` and amount 100.00
   - Create POs:
     a) Open, IBAN: `NL99XXXX0000000000`, total: 105.00 (diff 5, no IBAN match)
     b) Open, IBAN: `NL00ABCD1234567890`, total: 150.00 (diff 50, IBAN match)
     c) Open, IBAN: `NL00ABCD1234567890`, total: 90.00 (diff 10, IBAN match)
   - Expected order: c (IBAN match, diff 10), b (IBAN match, diff 50), a (no match, diff 5)
   - IBAN match ranks higher than amount proximity

Note: For feature tests that assert Select option ordering from Livewire, you may need to inspect the Livewire component's data/properties to verify the option order, or alternatively test the query scope ordering logic directly via unit tests (see below).

#### Test File (NEW): `tests/Unit/Domain/BankTransactions/InvoiceRelevancyOrderingTest.php`

**Test methods**:

1. `test_open_or_pending_scope_filters_correct_statuses()`
   - Unit test using an in-memory SQLite database (extends FeatureTestCase for DB access)
   - Create Invoices with various statuses
   - Query with `->openOrPending()` scope
   - Assert only Open and Pending are returned

2. `test_order_by_amount_proximity_scope_orders_correctly()`
   - Create Invoices with lines producing different totals
   - Query with `->orderByAmountProximity($target)`
   - Assert correct order

#### Test File (NEW): `tests/Unit/Domain/BankTransactions/PurchaseOrderRelevancyOrderingTest.php`

**Test methods**:

1. `test_open_or_pending_scope_filters_correct_statuses()`
2. `test_order_by_relevancy_scope_orders_by_iban_then_amount()`
3. `test_order_by_relevancy_handles_null_creditor_iban()`

### TODO 2 Tests

#### Test File (NEW): `tests/Unit/Domain/Invoices/InvoiceServiceImplTest.php`

Extends `Tests\UnitTestCase`. Uses Mockery, Expectation classes.

**Test methods**:

1. `test_mark_as_paid_updates_status_and_creates_bookkeeping_records()`
   - Create `InvoiceRepositoryExpectation` mock with `expectsMarkAsPaid($invoiceId)`
   - Create `BookkeepingRecordRepositoryExpectation` mock with `expectsCreateForInvoice($invoiceId)`
   - Instantiate `InvoiceServiceImpl` with both mocks
   - Call `markAsPaid($invoiceId)`
   - Assert both expectations were met

#### Test File (NEW): `tests/Unit/Domain/Invoices/InvoiceServiceExpectation.php`

Follows the expectation pattern from existing tests:

```php
final readonly class InvoiceServiceExpectation
{
    private function __construct(
        public MockInterface&InvoiceService $mock,
    ) {}

    public static function create(): self
    {
        return new self(Mockery::mock(InvoiceService::class));
    }

    public function expectsMarkAsPaid(InvoiceId $id): void
    {
        $this->mock
            ->expects('markAsPaid')
            ->with(equalTo($id));
    }
}
```

#### File to Modify: `tests/Unit/Domain/Invoices/InvoiceRepositoryExpectation.php`

Wait - there is no `InvoiceRepositoryExpectation` that matches the `InvoiceRepository` interface. Looking at the existing tests, there are `CreateInvoiceExpectation` (mocks `InvoiceRepository`) and `InvoiceRepositoryExpectation` (mocks `InvoiceNumberRepository`).

**Check**: The existing `tests/Unit/Domain/Invoices/CreateInvoiceExpectation.php` mocks `InvoiceRepository` but is named differently. We should add `expectsMarkAsPaid` to the existing expectation that mocks `InvoiceRepository`.

Let me verify: `CreateInvoiceExpectation` mocks `InvoiceRepository`:
```php
private function __construct(public MockInterface&InvoiceRepository $mock) {}
```

So we should add `expectsMarkAsPaid(InvoiceId $id): void` to this class.

**Actually**, looking more carefully at the naming convention, `CreateInvoiceExpectation` is specifically for create-related operations. We should create a new `InvoiceRepositoryExpectation` that covers the full `InvoiceRepository` interface, or just add to the existing one. Given that the convention uses expectation classes per interface (e.g., `BookkeepingRecordRepositoryExpectation`), let's rename or add. The simplest approach: **add `expectsMarkAsPaid` to `CreateInvoiceExpectation`** since it already mocks `InvoiceRepository`.

Wait, actually, since it's named `CreateInvoiceExpectation` (singular purpose), and the mock type is `MockInterface&InvoiceRepository`, it's fine to add more methods to it. Many expectation classes cover multiple methods of the same interface.

**File to Modify**: `tests/Unit/Domain/Invoices/CreateInvoiceExpectation.php`

Add method:
```php
public function expectsMarkAsPaid(InvoiceId $id): void
{
    $this->mock
        ->expects('markAsPaid')
        ->with(equalTo($id));
}
```

#### File to Modify: `tests/Unit/Domain/Bookkeeping/BookkeepingRecordRepositoryExpectation.php`

Add method:
```php
public function expectsCreateForInvoice(InvoiceId $id): void
{
    $this->mock
        ->expects('createForInvoice')
        ->with(equalTo($id));
}
```

Add import for `InvoiceId`.

#### Test File (NEW): `tests/Unit/Domain/BankTransactions/BankTransactionServiceImplTest.php`

Extends `Tests\UnitTestCase`. Uses Mockery, Expectation classes.

**Dependencies to mock**:
- `BankTransactionRepository` (using `BankTransactionRepositoryExpectation`)
- `InvoiceService` (using `InvoiceServiceExpectation`)
- `PurchaseOrderService` (using `PurchaseOrderServiceExpectation`)

**Test methods**:

1. `test_attach_invoice_marks_as_paid_then_attaches()`
   - Mock `InvoiceService::markAsPaid` expectation
   - Mock `BankTransactionRepository::attachInvoice` expectation
   - Call `attachInvoice()`
   - Assert expectations met in order (using Mockery's `ordered()` if needed)

2. `test_attach_purchase_order_marks_as_paid_then_attaches()`
   - Mock `PurchaseOrderService::markAsPaid` expectation
   - Mock `BankTransactionRepository::attachPurchaseOrder` expectation
   - Call `attachPurchaseOrder()`
   - Assert expectations met

3. `test_attach_invoice_rolls_back_on_failure()`
   - Mock `InvoiceService::markAsPaid` to throw an exception
   - Assert exception is thrown
   - Verify `BankTransactionRepository::attachInvoice` was never called

#### Test File (NEW): `tests/Unit/Domain/BankTransactions/BankTransactionServiceExpectation.php`

Not needed since `BankTransactionServiceImplTest` directly tests the implementation without mocking the service itself.

#### Test File (NEW): `tests/Unit/Domain/PurchaseOrders/PurchaseOrderServiceExpectation.php`

Follows existing pattern. Mock for `PurchaseOrderService`:

```php
final readonly class PurchaseOrderServiceExpectation
{
    private function __construct(
        public MockInterface&PurchaseOrderService $mock,
    ) {}

    public static function create(): self
    {
        return new self(Mockery::mock(PurchaseOrderService::class));
    }

    public function expectsMarkAsPaid(PurchaseOrderId $id): void
    {
        $this->mock
            ->expects('markAsPaid')
            ->with(equalTo($id));
    }

    public function expectsMarkAsPending(PurchaseOrderId $id): void
    {
        $this->mock
            ->expects('markAsPending')
            ->with(equalTo($id));
    }
}
```

#### Test File to Modify: `tests/Feature/Filament/BankingTransaction/BankingTransactionResourceTest.php`

**Test methods to add** (for TODO 2 integration):

1. `test_attaching_invoice_marks_it_as_paid()`
   - Create a BankingTransaction
   - Create an Invoice with status Open and lines (so it has a total)
   - Mount ViewBankingTransaction, navigate to InvoicesRelationManager
   - Call the attach action with the invoice ID
   - Assert the invoice status is now Paid (refresh from DB)
   - Assert bookkeeping records were created for this invoice (check `bookkeeping_records` table)

2. `test_attaching_invoice_links_bookkeeping_to_banking_transaction()`
   - Create BankingTransaction, Invoice (Open, with lines)
   - Attach the invoice
   - Assert the bookkeeping records have `banking_transaction_id` set to the BT ID
   - This verifies the full chain: markAsPaid creates bookkeeping, then attachInvoice links them

3. `test_attaching_purchase_order_marks_it_as_paid()`
   - Create BankingTransaction, PurchaseOrder (Open, with lines, with creditor_iban)
   - Mount ViewBankingTransaction, navigate to PurchaseOrdersRelationManager
   - Call the attach action
   - Assert PO status is now Paid
   - Assert bookkeeping records were created

4. `test_attaching_purchase_order_links_bookkeeping_to_banking_transaction()`
   - Verify bookkeeping records have `banking_transaction_id` set

5. `test_attach_invoice_does_not_show_paid_or_declined_invoices()` (TODO 1 + TODO 2 combined)
   - Already covered by TODO 1 tests

### All Test Files Summary

| File | Action | Purpose |
|------|--------|---------|
| `tests/Feature/Filament/BankingTransaction/BankingTransactionResourceTest.php` | MODIFY | Add feature tests for filtering, ordering, mark-as-paid integration |
| `tests/Unit/Domain/BankTransactions/InvoiceRelevancyOrderingTest.php` | CREATE | Unit test invoice scope ordering with DB |
| `tests/Unit/Domain/BankTransactions/PurchaseOrderRelevancyOrderingTest.php` | CREATE | Unit test PO scope ordering with DB |
| `tests/Unit/Domain/Invoices/InvoiceServiceImplTest.php` | CREATE | Unit test InvoiceService::markAsPaid() |
| `tests/Unit/Domain/Invoices/InvoiceServiceExpectation.php` | CREATE | Mock expectation for InvoiceService |
| `tests/Unit/Domain/Invoices/CreateInvoiceExpectation.php` | MODIFY | Add expectsMarkAsPaid() |
| `tests/Unit/Domain/Bookkeeping/BookkeepingRecordRepositoryExpectation.php` | MODIFY | Add expectsCreateForInvoice() |
| `tests/Unit/Domain/BankTransactions/BankTransactionServiceImplTest.php` | CREATE | Unit test BankTransactionService orchestration |
| `tests/Unit/Domain/PurchaseOrders/PurchaseOrderServiceExpectation.php` | CREATE | Mock expectation for PurchaseOrderService |

---

## Edge Cases and Considerations

### For TODO 2

| Edge Case | Handling |
|-----------|----------|
| Invoice already Paid when attaching | `markAsPaid` does status update (no-op for already Paid); bookkeeping is idempotent (`whereNotExists` guard) |
| PO already Paid when attaching | Same as above |
| Invoice has no lines (total = 0) | Bookkeeping inserts 0.00 amounts (valid); COALESCE in subquery handles null |
| Invoice was never in a batch (no prior bookkeeping) | `createForInvoice` creates bookkeeping from scratch, same as `createForPurchaseOrder` |
| Invoice was in a batch that was closed (bookkeeping already exists) | `whereNotExists` guard prevents duplicate bookkeeping records |
| Transaction rollback on markAsPaid failure | `DB::transaction()` ensures both operations roll back |
| Transaction rollback on attach failure | Same |
| Multiple simultaneous attaches | Database transaction isolation; unique constraint on `banking_transaction_references` prevents double-attach |
| Invoice with Declined status | Already excluded by the Open/Pending filter in the Select (TODO 1), so shouldn't reach this code path |

### For Bookkeeping Records

- `createForInvoice` creates records with `reference_type = Invoice::class` and `reference_id = invoice.id`
- When `BankTransactionRepository::attachInvoice` runs after, it updates `bookkeeping_records.banking_transaction_id` to link them to the banking transaction
- This means the same bookkeeping record gets linked to the banking transaction during attachment (existing behavior in `BankTransactionDbRepository`)

---

## Complete File Change Summary

### New Files (Domain Layer)

1. `app/Domain/Invoices/InvoiceService.php` - Interface
2. `app/Domain/Invoices/InvoiceServiceImpl.php` - Implementation
3. `app/Domain/BankTransactions/BankTransactionService.php` - Interface
4. `app/Domain/BankTransactions/BankTransactionServiceImpl.php` - Implementation

### Modified Files (Domain Layer)

5. `app/Domain/Invoices/InvoiceRepository.php` - Add markAsPaid(InvoiceId): void
6. `app/Domain/Bookkeeping/BookkeepingRecordRepository.php` - Add createForInvoice(InvoiceId): void

### Modified Files (Infrastructure Layer)

7. `app/Infrastructure/Invoices/InvoiceRepositoryDb.php` - Implement markAsPaid()
8. `app/Infrastructure/Bookkeeping/BookkeepingRecordDbRepository.php` - Implement createForInvoice()

### Modified Files (Eloquent Models)

9. `app/Models/Invoice.php` - Add scopeOpenOrPending(), scopeOrderByAmountProximity()
10. `app/Models/PurchaseOrder.php` - Add scopeOpenOrPending(), scopeOrderByRelevancy()

### Modified Files (Filament Layer)

11. `app/Filament/Admin/Resources/BankingTransactions/Actions/AttachInvoiceAction.php` - Filter, order, use BankTransactionService
12. `app/Filament/Admin/Resources/BankingTransactions/Actions/AttachPurchaseOrderAction.php` - Filter, order, use BankTransactionService

### New Files (Tests)

13. `tests/Unit/Domain/BankTransactions/InvoiceRelevancyOrderingTest.php`
14. `tests/Unit/Domain/BankTransactions/PurchaseOrderRelevancyOrderingTest.php`
15. `tests/Unit/Domain/Invoices/InvoiceServiceImplTest.php`
16. `tests/Unit/Domain/Invoices/InvoiceServiceExpectation.php`
17. `tests/Unit/Domain/BankTransactions/BankTransactionServiceImplTest.php`
18. `tests/Unit/Domain/PurchaseOrders/PurchaseOrderServiceExpectation.php`

### Modified Files (Tests)

19. `tests/Feature/Filament/BankingTransaction/BankingTransactionResourceTest.php` - Add feature tests
20. `tests/Unit/Domain/Invoices/CreateInvoiceExpectation.php` - Add expectsMarkAsPaid()
21. `tests/Unit/Domain/Bookkeeping/BookkeepingRecordRepositoryExpectation.php` - Add expectsCreateForInvoice()

### Files NOT Modified
- `app/Domain/BankTransactions/BankTransactionRepository.php` - No changes needed; attach methods unchanged
- `app/Infrastructure/BankTransactions/BankTransactionDbRepository.php` - No changes; existing attach logic works after markAsPaid creates bookkeeping
- `app/Domain/PurchaseOrders/PurchaseOrderService.php` - No changes; existing markAsPaid is sufficient
- `app/Domain/PurchaseOrders/PurchaseOrderServiceImpl.php` - No changes
- Relation managers (InvoicesRelationManager, PurchaseOrdersRelationManager) - No changes; they use the actions which are being updated
