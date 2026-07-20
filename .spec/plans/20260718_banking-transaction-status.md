# Implementation Plan: Banking Transaction Status (Open / Completed)

**Date**: 2026-07-18  
**Feature**: Add a `status` field to banking transactions with `open` and `completed` states.  
**Goal**: Lock completed transactions from editing, defer bookkeeping record creation until completion, and enforce zero-unmatched-amount before completing.

---

## Overview of Current Architecture

```
BankTransactionServiceImpl::attachInvoice()
  ├── InvoiceServiceImpl::markAsPaid()
  │     ├── InvoiceRepositoryDb::markAsPaid()       → UPDATE status = 'paid'
  │     └── BookkeepingRecordDbRepository::createForInvoice()  → INSERT bookkeeping_records
  └── BankTransactionDbRepository::attachInvoice()
        ├── INSERT banking_transaction_references (pivot)
        └── UPDATE bookkeeping_records SET banking_transaction_id = $btId WHERE reference_type=Invoice AND reference_id=$invId

BankTransactionServiceImpl::attachPurchaseOrder()
  ├── PurchaseOrderServiceImpl::markAsPaid()
  │     ├── PurchaseOrderRepositoryDb::markAsPaid()  → UPDATE status = 'paid'
  │     └── BookkeepingRecordDbRepository::createForPurchaseOrder() → INSERT bookkeeping_records
  └── BankTransactionDbRepository::attachPurchaseOrder()
        ├── INSERT banking_transaction_references (pivot)
        └── UPDATE bookkeeping_records SET banking_transaction_id = $btId WHERE reference_type=PurchaseOrder AND reference_id=$poId
```

**Key observation**: Currently, `markAsPaid` + bookkeeping record creation happen at **attach time**. The new feature moves these to **complete time**.

---

## Files to Create

### 1. New Enum: `app/Domain/BankTransactions/BankTransactionStatus.php`

```php
<?php
declare(strict_types=1);

namespace App\Domain\BankTransactions;

enum BankTransactionStatus: string
{
    case Open = 'open';
    case Completed = 'completed';
}
```

### 2. New VO: `app/Domain/Invoices/InvoiceIdList.php`

A collection value object wrapping `InvoiceId[]`, following the `MemberIdList` pattern:

```php
<?php
declare(strict_types=1);

namespace App\Domain\Invoices;

use Webmozart\Assert\Assert;

final readonly class InvoiceIdList
{
    /** @param InvoiceId[] $ids */
    public function __construct(
        public array $ids,
    ) {
        /** @phpstan-ignore-next-line staticMethod.alreadyNarrowedType */
        Assert::allIsInstanceOf($ids, InvoiceId::class);
    }

    /** @param int[] $array */
    public static function fromArray(array $array): self
    {
        return new self(array_map(InvoiceId::create(...), $array));
    }
}
```

### 3. New VO: `app/Domain/PurchaseOrders/PurchaseOrderIdList.php`

Same pattern as `InvoiceIdList`, wrapping `PurchaseOrderId[]`:

```php
<?php
declare(strict_types=1);

namespace App\Domain\PurchaseOrders;

use Webmozart\Assert\Assert;

final readonly class PurchaseOrderIdList
{
    /** @param PurchaseOrderId[] $ids */
    public function __construct(
        public array $ids,
    ) {
        /** @phpstan-ignore-next-line staticMethod.alreadyNarrowedType */
        Assert::allIsInstanceOf($ids, PurchaseOrderId::class);
    }

    /** @param int[] $array */
    public static function fromArray(array $array): self
    {
        return new self(array_map(PurchaseOrderId::create(...), $array));
    }
}
```

### 5. New Migration: `database/migrations/XXXX_add_status_to_banking_transactions_table.php`

```php
Schema::table('banking_transactions', function (Blueprint $table): void {
    $table->string('status')->default('open')->after('import_hash');
    $table->index('status');
});
```
- Timestamp should be after `2026_07_05_074926` and before any later migrations.
- Defaults all existing records to `open`.

### 6. New Exception: `app/Domain/BankTransactions/CouldNotCompleteTransaction.php`

A domain exception thrown when the transaction cannot be completed because unmatched amount is non-zero. This should extend `\RuntimeException` and accept the `BankingTransactionId` and amount for a descriptive message.

### 7. New Action: `app/Filament/Admin/Resources/BankingTransactions/Actions/CompleteBankingTransactionAction.php`

Filament action that triggers the `complete()` flow. Only shown when:
- The transaction status is `open`
- The unmatched amount is zero (to prevent errors—also validated server-side)

The action calls `BankTransactionService::complete()`.

### 8. New Page: `app/Filament/Admin/Resources/BankingTransactions/Pages/EditBankingTransaction.php`

A standard Filament `EditRecord` page for editing banking transactions. Follows the same pattern as other Edit pages in the project (e.g., `EditInvoice`, `EditPurchaseOrder`):

```php
<?php
declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankingTransactions\Pages;

use App\Filament\Admin\Resources\BankingTransactions\BankingTransactionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

final class EditBankingTransaction extends EditRecord
{
    protected static string $resource = BankingTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
```

This page must also be registered in `BankingTransactionResource::getPages()` (see step 12b below).

---

## Files to Modify

### 5. Model: `app/Models/BankingTransaction.php`

**Changes**:

a) Add import for `BankTransactionStatus`, `InvoiceLine`, `PurchaseOrderLine`, and `DB` facade.

b) Add `status` to the `casts()` method:
```php
'status' => BankTransactionStatus::class,
```

c) Complete rewrite of `unmatchedAmount()` to compute from pivot table relations instead of bookkeeping records:
```php
protected function unmatchedAmount(): Attribute
{
    return Attribute::get(function (): float {
        $btId = $this->id;

        $invoiceTotal = (float) InvoiceLine::query()
            ->whereIn('invoice_id', function ($query) use ($btId) {
                $query->select('reference_id')
                    ->from('banking_transaction_references')
                    ->where('banking_transaction_id', $btId)
                    ->where('reference_type', Invoice::class);
            })
            ->rawValue('COALESCE(SUM(price * quantity), 0)');

        $poTotal = (float) PurchaseOrderLine::query()
            ->whereIn('purchase_order_id', function ($query) use ($btId) {
                $query->select('reference_id')
                    ->from('banking_transaction_references')
                    ->where('banking_transaction_id', $btId)
                    ->where('reference_type', PurchaseOrder::class);
            })
            ->rawValue('COALESCE(SUM(price), 0)');

        return (float) $this->amount - (float) $invoiceTotal + (float) $poTotal;
    });
}
```


d) Add helper method:
```php
public function isCompleted(): bool
{
    return $this->status === BankTransactionStatus::Completed;
}
```

e) Add `@property` PHPDoc for `$status`.

### 6. Domain Service Interface: `app/Domain/BankTransactions/BankTransactionService.php`

Add method:
```php
public function complete(BankTransactionId $bankTransactionId): void;
```

### 7. Domain Service Implementation: `app/Domain/BankTransactions/BankTransactionServiceImpl.php`

**Critical changes**:

a) `attachInvoice()` — Remove the call to `$this->invoiceService->markAsPaid()`. Only call `$this->repository->attachInvoice()`.

b) `attachPurchaseOrder()` — Remove the call to `$this->purchaseOrderService->markAsPaid()`. Only call `$this->repository->attachPurchaseOrder()`.

c) Add `complete()` method — uses list VOs returned by the repository instead of iterating IDs:
```php
public function complete(BankTransactionId $bankTransactionId): void
{
    $invoiceIdList = $this->repository->getAttachedInvoiceIds($bankTransactionId);
    $purchaseOrderIdList = $this->repository->getAttachedPurchaseOrderIds($bankTransactionId);

    $this->invoiceService->markAsPaid($invoiceIdList);
    $this->purchaseOrderService->markAsPaid($purchaseOrderIdList);

    $this->repository->complete($bankTransactionId);
}
```

**Important**: The order matters. `markAsPaid()` must be called BEFORE `repository->complete()`, because `markAsPaid` creates the bookkeeping records, and `complete()` links them to the banking transaction.

#### 7a. Cascading Signature Changes

Because `complete()` now passes `InvoiceIdList` and `PurchaseOrderIdList` VOs (instead of iterating single IDs), the downstream interfaces and implementations must be updated to accept these list VOs:

**InvoiceService** (`app/Domain/Invoices/InvoiceService.php`):
```php
// BEFORE:
public function markAsPaid(InvoiceId $id): void;
// AFTER:
public function markAsPaid(InvoiceIdList $ids): void;
```

**InvoiceServiceImpl** (`app/Domain/Invoices/InvoiceServiceImpl.php`):
```php
public function markAsPaid(InvoiceIdList $ids): void
{
    $this->invoiceRepository->markAsPaid($ids);
    $this->bookkeepingRepository->createForInvoice($ids);
}
```

**PurchaseOrderService** (`app/Domain/PurchaseOrders/PurchaseOrderService.php`):
```php
// BEFORE:
public function markAsPaid(PurchaseOrderId $id): void;
// AFTER:
public function markAsPaid(PurchaseOrderIdList $ids): void;
```

**PurchaseOrderServiceImpl** (`app/Domain/PurchaseOrders/PurchaseOrderServiceImpl.php`):
```php
public function markAsPaid(PurchaseOrderIdList $ids): void
{
    $this->repository->markAsPaid($ids);
    $this->bookkeepingRepository->createForPurchaseOrder($ids);
}
```

**InvoiceRepository** (`app/Domain/Invoices/InvoiceRepository.php`):
```php
// BEFORE:
public function markAsPaid(InvoiceId $id): void;
// AFTER:
public function markAsPaid(InvoiceIdList $ids): void;
```

**InvoiceRepositoryDb** (`app/Infrastructure/Invoices/InvoiceRepositoryDb.php`):
```php
public function markAsPaid(InvoiceIdList $ids): void
{
    Invoice::query()
        ->whereIn('id', array_map(fn (InvoiceId $id) => $id->value, $ids->ids))
        ->update(['status' => InvoiceStatus::Paid]);
}
```

**PurchaseOrderRepository** (`app/Domain/PurchaseOrders/PurchaseOrderRepository.php`):
```php
// BEFORE:
public function markAsPaid(PurchaseOrderId $id): void;
// AFTER:
public function markAsPaid(PurchaseOrderIdList $ids): void;
```

**PurchaseOrderRepositoryDb** (`app/Infrastructure/PurchaseOrders/PurchaseOrderRepositoryDb.php`):
```php
public function markAsPaid(PurchaseOrderIdList $ids): void
{
    PurchaseOrder::query()
        ->whereIn('id', array_map(fn (PurchaseOrderId $id) => $id->value, $ids->ids))
        ->update(['status' => PurchaseOrderStatus::Paid]);
}
```

**BookkeepingRecordRepository** (`app/Domain/Bookkeeping/BookkeepingRecordRepository.php`):
```php
// BEFORE:
public function createForInvoice(InvoiceId $id): void;
public function createForPurchaseOrder(PurchaseOrderId $id): void;
// AFTER:
public function createForInvoice(InvoiceIdList $ids): void;
public function createForPurchaseOrder(PurchaseOrderIdList $ids): void;
```

**BookkeepingRecordDbRepository** (`app/Infrastructure/Bookkeeping/BookkeepingRecordDbRepository.php`):
Both `createForInvoice` and `createForPurchaseOrder` change from `WHERE id = $id->value` to `WHERE IN (extracted IDs from the list)`:
```php
public function createForInvoice(InvoiceIdList $ids): void
{
    $idValues = array_map(fn (InvoiceId $id) => $id->value, $ids->ids);
    // ... same insert using ->whereIn('invoices.id', $idValues)
}

public function createForPurchaseOrder(PurchaseOrderIdList $ids): void
{
    $idValues = array_map(fn (PurchaseOrderId $id) => $id->value, $ids->ids);
    // ... same insert using ->whereIn('purchase_orders.id', $idValues)
}
```

**Note about `PurchaseOrderServiceImpl::markAsPending`**: This method currently calls `$this->bookkeepingRepository->createForPurchaseOrder($id)` with a single `PurchaseOrderId`. It is only used outside the bank transaction flow, so it can remain unchanged for now. If the single-ID `createForPurchaseOrder` is removed, a temporary local loop can bridge the gap:

```php
// Option: keep single-ID overload, or convert at call site:
public function markAsPending(PurchaseOrderId $id): void
{
    $this->repository->markAsPending($id);
    $this->bookkeepingRepository->createForPurchaseOrder(
        new PurchaseOrderIdList([$id])
    );
}
```

### 8. Domain Repository Interface: `app/Domain/BankTransactions/BankTransactionRepository.php`

Add methods:
```php
public function getAttachedInvoiceIds(BankTransactionId $bankTransactionId): \App\Domain\Invoices\InvoiceIdList;

public function getAttachedPurchaseOrderIds(BankTransactionId $bankTransactionId): \App\Domain\PurchaseOrders\PurchaseOrderIdList;

public function complete(BankTransactionId $bankTransactionId): void;
```

### 9. Infrastructure Repository: `app/Infrastructure/BankTransactions/BankTransactionDbRepository.php`

**Critical changes**:

a) `attachInvoice()` — Remove the `UPDATE bookkeeping_records SET banking_transaction_id` query. Only create the pivot via `syncWithoutDetaching`.

b) `detachInvoice()` — Remove the `UPDATE bookkeeping_records SET banking_transaction_id = null` query. Only detach from pivot.

c) `attachPurchaseOrder()` — Remove the `UPDATE bookkeeping_records SET banking_transaction_id` query. Only create the pivot.

d) `detachPurchaseOrder()` — Remove the `UPDATE bookkeeping_records SET banking_transaction_id = null` query. Only detach from pivot.

e) Add `getAttachedInvoiceIds()`:
```php
public function getAttachedInvoiceIds(BankTransactionId $bankTransactionId): InvoiceIdList
{
    $ids = BankingTransaction::query()
        ->findOrFail($bankTransactionId->value)
        ->invoices()
        ->pluck('reference_id')
        ->map(static fn (int $id) => InvoiceId::create($id))
        ->all();

    return new InvoiceIdList($ids);
}
```

f) Add `getAttachedPurchaseOrderIds()` (same pattern, returns `PurchaseOrderIdList`).

g) Add `complete()`:
```php
public function complete(BankTransactionId $bankTransactionId): void
{
    DB::transaction(function () use ($bankTransactionId): void {
        $bt = BankingTransaction::query()
            ->with(['invoices.lines', 'purchaseOrders.lines'])
            ->findOrFail($bankTransactionId->value);

        // Verify unmatched_amount is zero using same formula as model
        $invoiceTotal = $bt->invoices->sum(fn (Invoice $i) => $i->total->price);
        $poTotal = $bt->purchaseOrders->sum(fn (PurchaseOrder $po) => $po->total->price);
        $unmatched = (float) $bt->amount - $invoiceTotal + $poTotal;

        if (abs($unmatched) >= 0.01) {
            throw new CouldNotCompleteTransaction($bt);
        }

        // Set status to completed
        $bt->update(['status' => BankTransactionStatus::Completed]);

        // Link bookkeeping records for all attached invoices
        $invoiceIds = $bt->invoices->pluck('id');
        if ($invoiceIds->isNotEmpty()) {
            BookkeepingRecord::query()
                ->where('reference_type', Invoice::class)
                ->whereIn('reference_id', $invoiceIds)
                ->update(['banking_transaction_id' => $bankTransactionId->value]);
        }

        // Link bookkeeping records for all attached purchase orders
        $poIds = $bt->purchaseOrders->pluck('id');
        if ($poIds->isNotEmpty()) {
            BookkeepingRecord::query()
                ->where('reference_type', PurchaseOrder::class)
                ->whereIn('reference_id', $poIds)
                ->update(['banking_transaction_id' => $bankTransactionId->value]);
        }
    });
}
```

**Note about `BookkeepingRecordDbRepository::createForInvoice` and `createForPurchaseOrder`**: These methods already use `whereNotExists` to prevent duplicate bookkeeping records. Calling `markAsPaid` multiple times on the same invoice/PO will not create duplicate records. The `complete()` method links ALL bookkeeping records for the attached entities, including any that may have been created previously.

### 10. Filament Form: `app/Filament/Admin/Resources/BankingTransactions/Schemas/BankingTransactionForm.php`

Add a `status` field:
```php
TextInput::make('status')
    ->label(__('labels.status'))
    ->disabled()
    ->dehydrated(false)
    ->hiddenOn('create'),
```

### 11. Filament Table: `app/Filament/Admin/Resources/BankingTransactions/Tables/BankingTransactionsTable.php`

Add a status column with badge styling:
```php
TextColumn::make('status')
    ->label(__('labels.status'))
    ->badge()
    ->color(fn (string $state): string => match ($state) {
        'open' => 'warning',
        'completed' => 'success',
    })
    ->sortable(),
```

### 12. Filament View & Edit Pages

#### 12a. New Edit Page: `app/Filament/Admin/Resources/BankingTransactions/Pages/EditBankingTransaction.php`

Create the edit page (see "Files to Create" section 8 above). Follows the standard `EditRecord` pattern used by all other resources in the project.

#### 12b. Register Edit Page in Resource: `app/Filament/Admin/Resources/BankingTransactions/BankingTransactionResource.php`

Add the `use` import for `EditBankingTransaction` and register the edit route in `getPages()`:

```php
use App\Filament\Admin\Resources\BankingTransactions\Pages\EditBankingTransaction;

// In getPages():
'edit' => EditBankingTransaction::route('/{record}/edit'),
'view' => ViewBankingTransaction::route('/{record}'),
```

This follows the same pattern as `PurchaseOrderResource`, `InvoiceResource`, and other resources that have both view and edit pages. The edit route uses `/{record}/edit` while view uses `/{record}`.

#### 12c. Update View Page: `app/Filament/Admin/Resources/BankingTransactions/Pages/ViewBankingTransaction.php`

Add `EditAction` and `CompleteBankingTransactionAction` to header actions:

```php
use App\Filament\Admin\Resources\BankingTransactions\Actions\CompleteBankingTransactionAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;

protected function getHeaderActions(): array
{   
    return [
        CompleteBankingTransactionAction::make(),
        EditAction::make(),
        DeleteAction::make(),
    ];
}
```

The `EditAction` provides navigation from the view page to the edit page, matching the pattern used by all other resources with both view and edit pages (e.g., `ViewPurchaseOrder` has `EditAction::make()` in its header actions).

When the transaction is completed, the `EditAction` and `DeleteAction` are automatically hidden by Filament if the policy's `update()` / `delete()` methods return `false` — see step 21 for the policy changes.

b) The `ViewBankingTransaction` page itself doesn't control relation managers directly; they check `$this->getOwnerRecord()`.

### 13. Relation Manager: `app/Filament/Admin/Resources/BankingTransactions/RelationManagers/InvoicesRelationManager.php`

Add conditional hiding of header/record actions:
```php
public function table(Table $table): Table
{
    /** @var BankingTransaction $owner */
    $owner = $this->getOwnerRecord();
    
    return $table
        ->columns([...])
        ->recordUrl(ViewOrEdit::route(InvoiceResource::class))
        ->headerActions(
            $owner->isCompleted() ? [] : [AttachInvoiceAction::make()]
        )
        ->recordActions(
            $owner->isCompleted() ? [] : [
                Action::make('detach')...
            ]
        );
}
```

### 14. Relation Manager: `app/Filament/Admin/Resources/BankingTransactions/RelationManagers/PurchaseOrdersRelationManager.php`

Same pattern as invoices — conditionally hide actions when `$owner->isCompleted()`.

### 15. Relation Manager: `app/Filament/Admin/Resources/BankingTransactions/RelationManagers/BookkeepingRecordsRelationManager.php`

Same pattern — conditionally hide attach/detach actions when `$owner->isCompleted()`.

### 16. Actions: `app/Filament/Admin/Resources/BankingTransactions/Actions/AttachInvoiceAction.php`

Add a check — should not appear when the transaction is completed. This is handled by the relation manager conditionally hiding it. But for extra safety, add:
```php
->visible(fn (RelationManager $livewire) => !$livewire->getOwnerRecord()->isCompleted())
```

### 17. Actions: `app/Filament/Admin/Resources/BankingTransactions/Actions/AttachPurchaseOrderAction.php`

Same as above — add `->visible()` check.

### 18. Actions: `app/Filament/Admin/Resources/BankingTransactions/Actions/AttachBookkeepingRecordAction.php`

Add same `->visible()` check.

### 19. Factory: `database/factories/BankingTransactionFactory.php`

Add default status:
```php
'status' => BankTransactionStatus::Open->value,
```

Add optional state:
```php
public function completed(): self
{
    return $this->state(['status' => BankTransactionStatus::Completed->value]);
}
```

### 20. Language File: `lang/nl/labels.php`

Add translations:
```php
'status' => 'Status',
'open' => 'Open',
'completed' => 'Afgerond',
'complete_transaction' => 'Markeer als afgerond',
'complete' => 'Afgerond',
'cannot_complete_unmatched' => 'Kan transactie niet afronden: het bedrag is niet volledig gekoppeld.',
```

### 21. Policy: `app/Policies/BankingTransactionPolicy.php`

Override `update()` and `delete()` to prevent editing or deleting completed transactions:

```php
<?php
declare(strict_types=1);

namespace App\Policies;

use App\Domain\BankTransactions\BankTransactionStatus;
use App\Models\BankingTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Override;

final class BankingTransactionPolicy extends ResourcePolicy
{
    protected static function permissionPrefix(): string
    {
        return 'banking_transactions';
    }

    #[Override]
    public function update(User $user, Model $record): bool
    {
        if ($record instanceof BankingTransaction && $record->isCompleted()) {
            return false;
        }

        return parent::update($user, $record);
    }

    #[Override]
    public function delete(User $user, Model $record): bool
    {
        if ($record instanceof BankingTransaction && $record->isCompleted()) {
            return false;
        }

        return parent::delete($user, $record);
    }
}
```

**Note**: The `$record` parameter is typed as `Model` in the parent class (`ResourcePolicy`), so we use `instanceof BankingTransaction` to safely check the `isCompleted()` method. The `isCompleted()` helper relies on the `status` cast to `BankTransactionStatus` enum (added in step 5b). The `use` import for `BankTransactionStatus` is included above for clarity, though the policy only calls `isCompleted()` on the model.

With these overrides, Filament automatically hides the `EditAction` and `DeleteAction` from both the view page and the edit page when the transaction is completed.

---

## Tests to Create/Update

### 22. Update Model Tests: `tests/Feature/Models/BankingTransactionTest.php`

a) Update existing `unmatched_amount` tests — they currently use `BookkeepingRecord` to set up matching. These need to use `Invoice` with lines + pivot instead, since `unmatched_amount` is now computed from the pivot table references.

b) Add tests:
- `test_unmatched_amount_uses_invoice_and_purchase_order_totals_from_pivot`
- `test_status_defaults_to_open`
- `test_is_completed_returns_true_when_status_is_completed`
- `test_is_completed_returns_false_when_status_is_open`

### 23. Update Filament Tests: `tests/Feature/Filament/BankingTransaction/BankingTransactionResourceTest.php`

**Critical updates required**:

a) Update `test_attaching_invoice_marks_it_as_paid` → Attaching should NO LONGER mark as paid. Rename to `test_attaching_invoice_does_not_mark_it_as_paid` and assert status remains `Open`.

b) Update `test_attaching_invoice_creates_bookkeeping_records` → Attaching should NO LONGER create bookkeeping records. Rename to `test_attaching_invoice_does_not_create_bookkeeping_records`.

c) Update `test_attaching_purchase_order_marks_it_as_paid` → Same as (a) for purchase orders.

d) Update `test_attaching_purchase_order_creates_bookkeeping_records` → Same as (b) for purchase orders.

e) Add new tests:
- `test_completing_transaction_marks_invoice_as_paid`: Attach invoice, complete, assert invoice is paid.
- `test_completing_transaction_marks_purchase_order_as_paid`: Attach PO, complete, assert PO is paid.
- `test_completing_transaction_creates_bookkeeping_records`: Attach invoice, complete, assert bookkeeping records exist.
- `test_completing_transaction_links_bookkeeping_records`: Attach invoice, complete, assert bookkeeping records have `banking_transaction_id` set.
- `test_cannot_complete_transaction_with_unmatched_amount`: Attach invoice with wrong amount, try to complete, assert exception/failure.
- `test_completing_transaction_sets_status_to_completed`: Attach invoice (matching amount), complete, assert status is `completed`.

### 24. Update Repository Tests: `tests/Feature/Infrastructure/BankTransactions/BankTransactionDbRepositoryTest.php`

a) Update `it_attaches_an_invoice_and_links_bookkeeping_records` → Should NO LONGER link bookkeeping records. Rename to `it_attaches_an_invoice` and only assert the pivot is created, no bookkeeping link.

b) Update `it_detaches_an_invoice_and_clears_bookkeeping_records` → Should NO LONGER clear bookkeeping records. Rename to `it_detaches_an_invoice` and only assert pivot is removed.

c) Update `it_attaches_a_purchase_order_and_links_bookkeeping_records` → Same as (a).

d) Update `it_detaches_a_purchase_order_and_clears_bookkeeping_records` → Same as (b).

e) Add new tests:
- `it_gets_attached_invoice_ids`
- `it_gets_attached_purchase_order_ids`
- `it_completes_a_banking_transaction`: Verify status is `completed`, bookkeeping records are linked.
- `it_throws_when_completing_with_unmatched_amount`

### 25. Update Mock Expectation: `tests/Unit/Domain/BankTransactions/BankTransactionRepositoryExpectation.php`

Add helper methods:
```php
public function expectsGetAttachedInvoiceIds(BankTransactionId $id, InvoiceIdList $return): void { ... }
public function expectsGetAttachedPurchaseOrderIds(BankTransactionId $id, PurchaseOrderIdList $return): void { ... }
public function expectsComplete(BankTransactionId $id): void { ... }
```

---

## Implementation Steps (Recommended Order)

### Phase 1: Foundation (Domain + Database)
1. Create `BankTransactionStatus` enum
2. Create `InvoiceIdList` VO
3. Create `PurchaseOrderIdList` VO
4. Create migration for `status` column
5. Create `CouldNotCompleteTransaction` exception
6. Update `BankingTransaction` model (casts, `unmatchedAmount`, `isCompleted`, PHPDoc)
7. Update `BankingTransactionFactory` (default status, `completed()` state)

### Phase 2: Domain Service & Repository changes
8. Update `InvoiceRepository` interface + `InvoiceRepositoryDb` (accept `InvoiceIdList`)
9. Update `PurchaseOrderRepository` interface + `PurchaseOrderRepositoryDb` (accept `PurchaseOrderIdList`)
10. Update `BookkeepingRecordRepository` interface + `BookkeepingRecordDbRepository` (accept `InvoiceIdList` / `PurchaseOrderIdList`)
11. Update `InvoiceService` interface + `InvoiceServiceImpl` (accept `InvoiceIdList`, fix syntax error on line 21)
12. Update `PurchaseOrderService` interface + `PurchaseOrderServiceImpl` (accept `PurchaseOrderIdList`)
13. Update `BankTransactionRepository` interface (add `getAttachedInvoiceIds`, `getAttachedPurchaseOrderIds` returning list VOs, `complete`)
14. Update `BankTransactionDbRepository` (strip bookkeeping linking from attach/detach, implement new methods returning list VOs)
15. Update `BankTransactionService` interface (add `complete`)
16. Update `BankTransactionServiceImpl` (strip `markAsPaid` from attach, implement `complete` using list VOs)

### Phase 3: Filament UI
17. Update `BankingTransactionForm` (add status field)
18. Update `BankingTransactionsTable` (add status column)
19. Create `CompleteBankingTransactionAction`
20. Create `EditBankingTransaction` page (new `EditRecord` page)
21. Register `EditBankingTransaction` in `BankingTransactionResource::getPages()`
22. Update `ViewBankingTransaction` page (add `CompleteBankingTransactionAction`, `EditAction`, conditional `DeleteAction`)
23. Update all 3 relation managers (conditional hide actions when completed)
24. Update all 3 attach actions (add `->visible()` check)

### Phase 4: Policy, Translations & Tests
25. Update `BankingTransactionPolicy` (override `update()` and `delete()` to reject completed transactions)
26. Update `lang/nl/labels.php`
27. Update all existing tests
28. Add new tests for complete flow (including policy tests for completed transactions)
29. Run full test suite: `./Taskfile artisan test --compact`

---

## Key Design Decisions

1. **Bookkeeping records created at complete time, not attach time**: This means the `attachInvoice`/`attachPurchaseOrder` methods in the service no longer call `markAsPaid`. The `markAsPaid` calls happen inside `complete()`.
2. **`unmatched_amount` computed from pivot table + line items**: Uses subqueries through `banking_transaction_references` → invoice/PO lines. When relations are eager-loaded, it can use the loaded data for efficiency.
3. **Completion validation in the repository**: The `complete()` method verifies `unmatched == 0` server-side and throws `CouldNotCompleteTransaction` if not. The UI also hides the button, but server-side validation is the safety net.
4. **`whereNotExists` guard on bookkeeping creation**: `BookkeepingRecordDbRepository::createForInvoice` and `createForPurchaseOrder` already use `whereNotExists`. Calling `markAsPaid` during `complete()` won't duplicate bookkeeping records if they already exist.
5. **Existing records**: All `banking_transactions` get `status = 'open'` via migration default. Bookkeeping records that were already created (before this change) remain intact — they're just no longer managed by attach/detach. The `complete()` method links bookkeeping records for attached entities regardless of when they were created.
6. **Policy enforces completed lock at authorization level**: The `BankingTransactionPolicy` overrides `update()` and `delete()` to return `false` when the transaction is completed. This means:
   - Filament automatically hides `EditAction` and `DeleteAction` buttons for completed transactions
   - API-level access (if any) is also blocked
   - The "complete" action itself is not gated by `update()` (it uses its own authorization via the Filament action's `->visible()` check), so it remains available for `open` transactions as long as the user has `update_banking_transactions` permission
   - No new seeder entries needed — the existing `update_banking_transactions` and `delete_banking_transactions` permissions are sufficient

---

## Potential Risks

1. **Existing bookkeeping records**: If any bank transactions have invoices/POs attached via the old flow (where `markAsPaid` was called at attach time), those bookkeeping records already exist with `banking_transaction_id` set. After migration:
   - Those transactions won't have `status = 'completed'` (they default to `open`)
   - The bookkeeping records remain linked
   - `unmatched_amount` changes from using bookkeeping records to using pivot+line items — this could show different values for existing records
2. **N+1 query for `unmatched_amount`**: The subquery approach in the model attribute triggers 2 queries per row in list views. This is the same pattern as the existing code (which loaded `bookkeepingRecords`), so it's consistent but worth noting.
3. **Detaching after completion not prevented at DB level**: The repository's `detachInvoice`/`detachPurchaseOrder` still works on completed transactions. The locking is enforced at the UI layer only. If this is a concern, add a check in the repository methods.<｜end▁of▁thinking｜>

<｜｜DSML｜｜parameter name="filePath" string="true">/Users/vincent/git/personal/wsv-admin/.spec/plans/20260718_banking-transaction-status.md
