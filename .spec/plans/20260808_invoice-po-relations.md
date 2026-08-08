# Implementation Plan: Show Invoice & Purchase Order Relations

**Date**: 2026-08-08
**Goal**: Display related banking transactions and bookkeeping records on Invoice and Purchase Order detail views.

---

## Research Summary

### Current Data Model

```
banking_transaction_references (polymorphic pivot)
├── banking_transaction_id → BankingTransaction
├── reference_type          → 'App\Models\Invoice' | 'App\Models\PurchaseOrder'
└── reference_id            → Invoice.id | PurchaseOrder.id

bookkeeping_records
├── banking_transaction_id  → BankingTransaction (nullable)
├── reference_type/id       → polymorphic (nullable, can point to Invoice/PurchaseOrder)
├── cost_center_id          → CostCenter
├── year, amount_price, amount_vat, description
```

### Current Model Relationships

| Model | Relationship | Type | Exists? |
|-------|-------------|------|---------|
| Invoice | `bankingTransactions()` | morphToMany | ✅ Yes |
| Invoice | `bookkeepingRecords()` | morphMany via `reference` | ❌ Missing |
| PurchaseOrder | `bankingTransactions()` | morphToMany | ✅ Yes |
| PurchaseOrder | `bookkeepingRecords()` | morphMany via `reference` | ❌ Missing |
| BookkeepingRecord | `reference()` | morphTo | ✅ Yes |
| BookkeepingRecord | `bankingTransaction()` | belongsTo | ✅ Yes |

### Existing Pattern Reference

The `ViewBankingTransaction` page already shows relation managers for Invoices, PurchaseOrders, and BookkeepingRecords using `getRelationManagers()`. We replicate this pattern on Invoice and PurchaseOrder views, but read-only (no attach/detach).

### Key Design Decision

Bookkeeping records relate to Invoices/PurchaseOrders via TWO paths:
1. **Direct**: `bookkeeping_records.reference_type/id` points directly to Invoice/PurchaseOrder
2. **Indirect**: via `banking_transaction_id` → `banking_transaction_references` → Invoice/PurchaseOrder

The relation managers will show path 1 (direct). Path 2 is visible by navigating to the BankingTransaction view, which already has its own BookkeepingRecords relation manager.

---

## Tasks

### Task 1: Add `bookkeepingRecords()` to Invoice model

**File**: `app/Models/Invoice.php`

Add a `morphMany` relationship after the existing `bankingTransactions()` method:

```php
/** @return \Illuminate\Database\Eloquent\Relations\MorphMany<BookkeepingRecord, $this> */
public function bookkeepingRecords(): MorphMany
{
    return $this->morphMany(BookkeepingRecord::class, 'reference');
}
```

Add import for `MorphMany` at the top (line ~18 area):
```php
use Illuminate\Database\Eloquent\Relations\MorphMany;
```

### Task 2: Add `bookkeepingRecords()` to PurchaseOrder model

**File**: `app/Models/PurchaseOrder.php`

Add a `morphMany` relationship after the existing `bankingTransactions()` method:

```php
/** @return \Illuminate\Database\Eloquent\Relations\MorphMany<BookkeepingRecord, $this> */
public function bookkeepingRecords(): MorphMany
{
    return $this->morphMany(BookkeepingRecord::class, 'reference');
}
```

Same import addition as Invoice.

### Task 3: Create `InvoiceBankingTransactionsRelationManager`

**New file**: `app/Filament/Admin/Resources/Invoices/RelationManagers/InvoiceBankingTransactionsRelationManager.php`

- Extends `Filament\Resources\RelationManagers\RelationManager`
- Relationship: `bankingTransactions`
- Read-only table showing:
  - `description` (label: `labels.description`)
  - `date` (label: `labels.date`, date format, sortable)
  - `amount` (label: `labels.total`, money format `EUR`, align end)
- `recordUrl()`: link to BankingTransactionResource view/edit (use `ViewOrEdit::route(...)`)
- No header actions (attach/detach done from BankingTransaction side)
- Labels: `labels.banking_transaction` / `labels.banking_transactions`
- Add `#[On('refresh')] public function refresh(): void {}`

### Task 4: Create `InvoiceBookkeepingRecordsRelationManager`

**New file**: `app/Filament/Admin/Resources/Invoices/RelationManagers/InvoiceBookkeepingRecordsRelationManager.php`

- Extends `Filament\Resources\RelationManagers\RelationManager`
- Relationship: `bookkeepingRecords`
- Read-only table showing:
  - `year` (label: `labels.book_year`)
  - `costCenter.title` (label: `labels.cost_center`)
  - `description` (label: `labels.description`)
  - `amount` (label: `labels.price`, money format `EUR`)
- `recordUrl()`: link to BookkeepingRecordResource view/edit (use `ViewOrEdit::route(...)`)
- No header actions
- Use `getTitle()` override returning `__('labels.bookkeeping_records')`
- Add `#[On('refresh')] public function refresh(): void {}`

### Task 5: Create `PurchaseOrderBankingTransactionsRelationManager`

**New file**: `app/Filament/Admin/Resources/PurchaseOrders/RelationManagers/PurchaseOrderBankingTransactionsRelationManager.php`

Same pattern as Task 3 but in the `PurchaseOrders\RelationManagers` namespace:

- Relationship: `bankingTransactions`
- Same columns and configuration
- Labels: `labels.banking_transaction` / `labels.banking_transactions`

### Task 6: Create `PurchaseOrderBookkeepingRecordsRelationManager`

**New file**: `app/Filament/Admin/Resources/PurchaseOrders/RelationManagers/PurchaseOrderBookkeepingRecordsRelationManager.php`

Same pattern as Task 4 but in the `PurchaseOrders\RelationManagers` namespace:

- Relationship: `bookkeepingRecords`
- Same columns and configuration

### Task 7: Register relation managers in `ViewInvoice`

**File**: `app/Filament/Admin/Resources/Invoices/Pages/ViewInvoice.php`

Add `getRelationManagers()` method:

```php
#[Override]
public function getRelationManagers(): array
{
    return [
        \App\Filament\Admin\Resources\Invoices\RelationManagers\InvoiceBankingTransactionsRelationManager::class,
        \App\Filament\Admin\Resources\Invoices\RelationManagers\InvoiceBookkeepingRecordsRelationManager::class,
    ];
}
```

Also add:
```php
#[Override]
public function hasCombinedRelationManagerTabsWithContent(): bool
{
    return true;
}

#[Override]
public function getContentTabLabel(): string
{
    return __('labels.invoice');
}
```

This puts relations in tabs alongside the main content (matching BankingTransaction's UX pattern).

### Task 8: Register relation managers in `EditInvoice`

**File**: `app/Filament/Admin/Resources/Invoices/Pages/EditInvoice.php`

Add same `getRelationManagers()`, `hasCombinedRelationManagerTabsWithContent()`, and `getContentTabLabel()` as Task 7.

### Task 9: Register relation managers in `ViewPurchaseOrder`

**File**: `app/Filament/Admin/Resources/PurchaseOrders/Pages/ViewPurchaseOrder.php`

Add:

```php
#[Override]
public function getRelationManagers(): array
{
    return [
        \App\Filament\Admin\Resources\PurchaseOrders\RelationManagers\PurchaseOrderBankingTransactionsRelationManager::class,
        \App\Filament\Admin\Resources\PurchaseOrders\RelationManagers\PurchaseOrderBookkeepingRecordsRelationManager::class,
    ];
}

#[Override]
public function hasCombinedRelationManagerTabsWithContent(): bool
{
    return true;
}

#[Override]
public function getContentTabLabel(): string
{
    return __('labels.purchase_order');
}
```

### Task 10: Register relation managers in `EditPurchaseOrder`

**File**: `app/Filament/Admin/Resources/PurchaseOrders/Pages/EditPurchaseOrder.php`

Same as Task 9.

### Task 11: Add missing translation labels (if any needed)

**File**: `lang/nl/labels.php`

All needed labels already exist:
- `banking_transaction`, `banking_transactions` → ✅ lines 232-233
- `bookkeeping_records` → ✅ line 209
- `book_year` → ✅ line 207
- `cost_center` → ✅ line 84
- `description` → ✅ line 41
- `price` → ✅ line 43
- `date` → ✅ line 226
- `total` → ✅ line 33
- `invoice` → ✅ line 34
- `purchase_order` → ✅ line 216

No new labels required.

---

## Verification

### Tests

Run existing test suites to ensure no regressions:
```bash
./Taskfile artisan test --compact --filter=Invoice
./Taskfile artisan test --compact --filter=PurchaseOrder
```

If no existing tests cover relation manager rendering on these pages, consider adding a `->assertTableActionVisible()` or navigation test, but this is optional given the pattern is well-established from BankingTransaction.

### Manual Verification

1. Open an Invoice detail page → verify "Banking Transactions" and "Bookkeeping Records" tabs appear
2. Open a Purchase Order detail page → verify same tabs appear
3. Verify the tables show correct data and links to related resources work
4. Verify no actions (attach/detach) appear on these read-only relation managers

---

## Files Changed Summary

| File | Action |
|------|--------|
| `app/Models/Invoice.php` | Add `bookkeepingRecords()` morphMany |
| `app/Models/PurchaseOrder.php` | Add `bookkeepingRecords()` morphMany |
| `app/Filament/Admin/Resources/Invoices/RelationManagers/InvoiceBankingTransactionsRelationManager.php` | **New** |
| `app/Filament/Admin/Resources/Invoices/RelationManagers/InvoiceBookkeepingRecordsRelationManager.php` | **New** |
| `app/Filament/Admin/Resources/Invoices/Pages/ViewInvoice.php` | Add `getRelationManagers()` etc. |
| `app/Filament/Admin/Resources/Invoices/Pages/EditInvoice.php` | Add `getRelationManagers()` etc. |
| `app/Filament/Admin/Resources/PurchaseOrders/RelationManagers/PurchaseOrderBankingTransactionsRelationManager.php` | **New** |
| `app/Filament/Admin/Resources/PurchaseOrders/RelationManagers/PurchaseOrderBookkeepingRecordsRelationManager.php` | **New** |
| `app/Filament/Admin/Resources/PurchaseOrders/Pages/ViewPurchaseOrder.php` | Add `getRelationManagers()` etc. |
| `app/Filament/Admin/Resources/PurchaseOrders/Pages/EditPurchaseOrder.php` | Add `getRelationManagers()` etc. |

**Total**: 2 model edits, 4 new files, 4 page edits. No new labels needed. No migrations.
