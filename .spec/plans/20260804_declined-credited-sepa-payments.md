# Declined / Credited SEPA Payment Matching

**Date**: 2026-08-04
**Status**: Ready for implementation
**Related Todo**: #2 in `TODO.md`

## Overview

When a SEPA direct debit or credit transfer is declined/returned, the bank sends **two** banking transactions:
1. The **original payment** (e.g., +€100 incoming)
2. The **declined/credited reversal** (e.g., −€100 outgoing) that reverses it

These share the same absolute amount, opposite sign, same counterparty IBAN, and occur close together in time. Currently the system has **no notion of a reversal**. Each transaction lives independently and if one can't be matched to an invoice/PO, it ends up as `unresolvable`. This feature links them together so the admin can see that a pair cancels itself out.

## Current State Analysis

### What Exists

| Concept | Implementation |
|---------|---------------|
| **Transaction model** | `BankingTransaction` — date, amount, description, banking_account_number, status (`open`/`completed`), resolve_status (`unresolved`/`resolved`/`unresolvable`) |
| **Transaction references** | Polymorphic `banking_transaction_references` table — links to Invoice or PurchaseOrder only |
| **Transaction-to-transaction** | **None** — no way to link two transactions together |
| **Matching pipeline** | `MatchBankingTransactionsJob` → `BankTransactionService::resolveMatching()` → `TransactionMatchingService::findMatch()` — only matches to invoices (credit) or POs (debit) |
| **Invoice credits** | `credit_invoice_id` self-FK on invoices (for credit notes) — completely separate concept |
| **Invoice statuses** | `Open`, `Pending`, `Paid`, `Declined` |

### Key Files

| File | Role |
|------|------|
| `app/Models/BankingTransaction.php` | Eloquent model: invoices(), purchaseOrders(), bankAccount() relations |
| `app/Domain/BankTransactions/BankTransactionRepository.php` | Interface: CRUD, attach/detach, getUnresolvedIds, getMatchCriteriaForIds, markAsResolved/Unresolvable |
| `app/Infrastructure/BankTransactions/BankTransactionDbRepository.php` | Implementation |
| `app/Domain/BankTransactions/BankTransactionService.php` | Interface: attach, complete, resolveMatching |
| `app/Domain/BankTransactions/BankTransactionServiceImpl.php` | Implementation: orchestrates matching and completion |
| `app/Domain/BankTransactions/TransactionMatchingService.php` | Interface: findMatch(MatchCriteria): MatchResult |
| `app/Domain/BankTransactions/TransactionMatchingServiceImpl.php` | Implementation: amount>0 → findMatchingInvoice, amount≤0 → findMatchingPurchaseOrder |
| `app/Domain/BankTransactions/MatchCriteria.php` | DTO: date, amount, bankingAccountNumber |
| `app/Domain/BankTransactions/MatchResult.php` | DTO: isMatch, invoiceId, purchaseOrderId |
| `app/Domain/BankTransactions/BankTransactionStatus.php` | Enum: Open, Completed |
| `app/Domain/BankTransactions/ResolveStatus.php` | Enum: Unresolved, Resolved, Unresolvable |
| `app/Domain/Jobs/MatchBankingTransactionsJob.php` | Batch job: fetches unresolved, calls service |
| `app/Console/Commands/MatchBankingTransactionsCommand.php` | CLI entry point |
| `app/Filament/Admin/Resources/BankingTransactions/Pages/ViewBankingTransaction.php` | View page with actions and relation managers |
| `app/Filament/Admin/Resources/BankingTransactions/Pages/ListBankingTransactions.php` | List page with MT940 import |
| `app/Filament/Admin/Resources/BankingTransactions/Tables/BankingTransactionsTable.php` | Table columns |
| `app/Filament/Admin/Resources/BankingTransactions/Widgets/BankingTransactionStats.php` | Stats widget on view page |
| `app/Filament/Admin/Resources/BankingTransactions/Actions/CompleteBankingTransactionAction.php` | Complete action |
| `app/Filament/Admin/Resources/BankingTransactions/Actions/RetryMatchingAction.php` | Retry matching action |
| `database/migrations/2026_07_05_074926_create_banking_transactions_table.php` | Base table |
| `database/migrations/2026_07_05_075109_create_banking_transaction_references_table.php` | Polymorphic pivot |
| `database/factories/BankingTransactionFactory.php` | Factory |
| `tests/Unit/Domain/BankTransactions/BankTransactionServiceImplTest.php` | Service test |
| `tests/Unit/Domain/BankTransactions/TransactionMatchingServiceImplTest.php` | Matching service test |
| `tests/Unit/Domain/Jobs/MatchBankingTransactionsJobTest.php` | Job test |
| `tests/Feature/Models/BankingTransactionTest.php` | Model test |
| `tests/Feature/Filament/BankingTransaction/BankingTransactionResourceTest.php` | Filament test |

### Test Expectation Classes

| File | Purpose |
|------|---------|
| `tests/Unit/Domain/BankTransactions/BankTransactionRepositoryExpectation.php` | Mock expectations for repository |
| `tests/Unit/Domain/BankTransactions/BankTransactionServiceExpectation.php` | Mock expectations for service |
| `tests/Unit/Domain/BankTransactions/TransactionMatchingServiceExpectation.php` | Mock expectations for matching service |

---

## Requirements

1. **Detect reversal pairs** during the matching job: two transactions with opposite sign, same absolute amount (±0.01 tolerance), same banking account number, within ~30 days
2. **Link them** via a self-referencing foreign key on the `banking_transactions` table, and mark the reversal with `is_credit_transaction = true`
3. **Copy attached references** (invoices, purchase orders) from the original transaction to the reversal transaction — so the reversal "knows about" what it's reversing
4. **Mark original's invoices and purchase orders as Declined** — the payment was reversed, so these entities should reflect that
5. **Detach bookkeeping records** from the original transaction (set `banking_transaction_id` to NULL) — the transaction has been reverted
6. **Mark them as resolved** (they cancel each other out, no further action needed)
7. **`unmatched_amount` returns zero** for any transaction where `is_credit_transaction = true` — credit transactions should be ignored in reconciliation math
8. **Filter by `is_credit_transaction`** in the Filament table to easily show/hide credit transactions
9. **Show the relationship** in the Filament UI: on the view page, show if a transaction is reversed by another, and if a transaction reverses another
10. **Manual linking/unlinking**: Admin can manually link two transactions as a reversal pair via a Filament action
11. **Auto-matching**: After normal invoice/PO matching fails, try reversal matching before marking `unresolvable`

---

## Design Decisions

### Two New Columns

| Column | Type | Purpose |
|--------|------|---------|
| `reversed_by_transaction_id` | nullable FK → `banking_transactions.id` (`nullOnDelete`) | Links the reversal back to the original |
| `is_credit_transaction` | `boolean`, default `false` | Flags a transaction as a credit/reversal; used for filtering and to zero out `unmatched_amount` |

### Self-Referencing FK: `reversed_by_transaction_id`

- The **reversal** transaction points back to the **original** via `reversed_by_transaction_id`.
- When a pair is detected, the **newer** transaction (by date, or by `id` if same date) gets `reversed_by_transaction_id` set to the older one. It also gets `is_credit_transaction = true`.
- Both are marked `resolve_status = resolved`.
- The original keeps `is_credit_transaction = false` (it was the real payment, not the reversal).
- A transaction can only be reversed **once** (once `reversed_by_transaction_id` is set, it won't be changed by auto-matching). Manual override is possible.

### `is_credit_transaction` — Filtering & Math

- **Filtering**: Admin can toggle credit transactions on/off in the table via a `SelectFilter`.
- **`unmatched_amount`**: When `is_credit_transaction = true`, the `unmatchedAmount()` accessor returns `0.0`. The `matchedAmount()` accessor returns `$this->amount`. This means credit transactions are effectively "self-matched" — they never show a non-zero unmatched amount and don't block completion.
- This field is set **only** on the reversal transaction, not the original.

### Copy References & Decline on Link

When two transactions are linked as a reversal pair:

```
original (e.g. +€100, matched to Invoice #42)
   │
   ├── invoices: [Invoice #42]           → status set to Declined
   ├── purchaseOrders: []                → (if any, status set to Declined)
   └── bookkeepingRecords: [Record #7]   → banking_transaction_id set to NULL

   ↓ after linkReversal(), references are copied to the reversal

reversal (e.g. -€100, is_credit_transaction = true)
   ├── invoices: [Invoice #42]           ← copied from original
   ├── purchaseOrders: []                ← copied from original
   └── bookkeepingRecords: []            ← NOT copied (set to null on original)
```

This is done in a single DB transaction:

1. **Set the reversal link + flag**: `reversed_by_transaction_id` and `is_credit_transaction = true` on the reversal transaction
2. **Bulk-copy references**: All rows from `banking_transaction_references` where `banking_transaction_id = original.id` are bulk-inserted with `banking_transaction_id = reversal.id` using a single `INSERT ... SELECT`
3. **Set bookkeeping records to NULL**: `bookkeeping_records.banking_transaction_id` on the original is set to `NULL` — the transaction has been reverted, so the booking link is dissolved
4. **Decline linked invoices**: All invoices attached to the original are marked as `Declined` via `InvoiceService::markAsDeclined()`
5. **Decline linked purchase orders**: All purchase orders attached to the original are marked as `Declined` via `PurchaseOrderService::markAsDeclined()`

On `unlinkReversal()`, the copied references are removed from the reversal and the reversal flag is cleared. Invoices/POs stay `Declined` — unlinking doesn't undo the decline (manual admin action required). Bookkeeping records stay `NULL`.

### Reversal Matching in the Pipeline

The reversal matching happens **after** normal invoice/PO matching fails:

```
TransactionMatchingService::findMatch()
  → try invoice matching (amount > 0)
  → try purchase order matching (amount ≤ 0)
  → if no match: try reversal matching ← NEW
```

In `BankTransactionServiceImpl::resolveMatching()`, if `findMatch()` returns `MatchResult::none()`, we call a new `findReversalMatch()` path before marking `unresolvable`.

### MatchResult Extension

`MatchResult` gets a new factory method:

```php
public static function foundReversal(BankTransactionId $reversedById): self
```

And a new optional property `public ?BankTransactionId $reversedByTransactionId = null`.

---

## Implementation Plan

### Step 1: Database Migration

**File**: `database/migrations/2026_08_04_000000_add_reversal_columns_to_banking_transactions.php`

```php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banking_transactions', static function (Blueprint $table): void {
            $table->foreignId('reversed_by_transaction_id')
                ->nullable()
                ->constrained('banking_transactions')
                ->nullOnDelete();
            $table->boolean('is_credit_transaction')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('banking_transactions', static function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reversed_by_transaction_id');
            $table->dropColumn('is_credit_transaction');
        });
    }
};
```

### Step 2: Update Eloquent Model

**File**: `app/Models/BankingTransaction.php`

Add relationships, casts, and helpers:

```php
/** @return BelongsTo<BankingTransaction, $this> */
public function reversedBy(): BelongsTo
{
    return $this->belongsTo(self::class, 'reversed_by_transaction_id');
}

/** @return HasOne<BankingTransaction, $this> */
public function reversedTransaction(): HasOne
{
    return $this->hasOne(self::class, 'reversed_by_transaction_id');
}

public function isReversal(): bool
{
    return $this->reversed_by_transaction_id !== null;
}

public function isReversed(): bool
{
    return $this->reversedTransaction()->exists();
}

public function isCreditTransaction(): bool
{
    return $this->is_credit_transaction;
}
```

Add to `casts()`:
```php
'reversed_by_transaction_id' => 'integer',
'is_credit_transaction' => 'boolean',
```

**Override `matchedAmount()` and `unmatchedAmount()` for credit transactions:**

```php
protected function matchedAmount(): Attribute
{
    return Attribute::get(function (): float {
        // Credit transactions are self-matched — their amount is their matched amount
        if ($this->is_credit_transaction) {
            return (float) $this->reversedTransaction?->amount ?? 0.0;
        }

        $invoiceTotal = (float) InvoiceLine::query()
            ->whereIn('invoice_id', $this->invoices()->select('invoices.id'))
            ->sum(DB::raw('price * quantity'));

        $poTotal = PurchaseOrderLine::query()
            ->whereIn('purchase_order_id', $this->purchaseOrders()->select('purchase_orders.id'))
            ->sum(DB::raw('price'));

        $unattachedBookkeepingRecords = $this
            ->bookkeepingRecords()
            ->unattached()
            ->sum(DB::raw('amount_price'));

        return $invoiceTotal + $unattachedBookkeepingRecords - $poTotal;
    });
}

protected function unmatchedAmount(): Attribute
{
    return Attribute::get(fn (): float =>
        $this->is_credit_transaction
            ? 0.0
            : $this->amount - $this->matched_amount
    );
}
```

### Step 3: Add Repository Methods

**File**: `app/Domain/BankTransactions/BankTransactionRepository.php`

Add methods:

```php
/** Find a potential reversal pair for a given transaction */
public function findReversalMatch(MatchCriteria $criteria): ?BankTransactionId;

/** Link two transactions as reversal pair: copies references from original to reversal */
public function linkReversal(BankTransactionId $reversalId, BankTransactionId $originalId): void;

/** Remove reversal link and copied references */
public function unlinkReversal(BankTransactionId $reversalId): void;

/** Get the attached booking record IDs for a transaction (needed for reference copying) */
public function getAttachedBookkeepingRecordIds(BankTransactionId $bankTransactionId): array;
```

**File**: `app/Infrastructure/BankTransactions/BankTransactionDbRepository.php`

Implement the new methods:

```php
use Carbon\CarbonImmutable;

#[Override]
public function findReversalMatch(MatchCriteria $criteria): ?BankTransactionId
{
    $date = CarbonImmutable::instance($criteria->date);

    $opposite = BankingTransaction::query()
        ->whereNull('reversed_by_transaction_id')
        ->where('banking_account_number', $criteria->bankingAccountNumber)
        ->where('description', $criteria->description)
        ->whereRaw('ABS(amount + ?) <= 0.01', [$criteria->amount]) // opposite sign, same abs amount within tolerance
        ->whereDate('date', '>=', $date->subDays(56)) // within 8 weeks
        ->whereDate('date', '<=', $date)
        ->orderByRaw('ABS(amount + ?) ASC', [abs($criteria->amount)])
        ->first();

    if ($opposite === null) {
        return null;
    }

    return BankTransactionId::create($opposite->id);
}

#[Override]
public function linkReversal(BankTransactionId $reversalId, BankTransactionId $originalId): void
{
    DB::transaction(function () use ($reversalId, $originalId): void {
        // 1. Set reversal link + flag
        BankingTransaction::query()
            ->where('id', $reversalId->value)
            ->update([
                'reversed_by_transaction_id' => $originalId->value,
                'is_credit_transaction' => true,
            ]);

        // 2. Bulk-copy banking_transaction_references (invoices + purchase orders) from original to reversal
        DB::table('banking_transaction_references')->insertUsing(
            ['banking_transaction_id', 'reference_type', 'reference_id', 'created_at', 'updated_at'],
            DB::table('banking_transaction_references')
                ->selectRaw('?, reference_type, reference_id, ?, ?', [$reversalId->value, now(), now()])
                ->where('banking_transaction_id', $originalId->value)
        );

        // 3. Unlink the original from bookkeeping records as it has been reversed
        DB::table('bookkeeping_records')
            ->where('banking_transaction_id', $originalId->value)
            ->update(['banking_transaction_id' => null]);
    });
}

#[Override]
public function unlinkReversal(BankTransactionId $reversalId): void
{
    DB::transaction(function () use ($reversalId): void {
        /** @var BankingTransaction $reversal */
        $reversal = BankingTransaction::query()->findOrFail($reversalId->value);
        $originalId = $reversal->reversed_by_transaction_id;

        // 1. Remove copied banking_transaction_references
        DB::table('banking_transaction_references')
            ->where('banking_transaction_id', $reversalId->value)
            ->delete();

        // 3. Clear reversal link + flag
        $reversal->update([
            'reversed_by_transaction_id' => null,
            'is_credit_transaction' => false,
        ]);
    });
}

#[Override]
public function getAttachedBookkeepingRecordIds(BankTransactionId $bankTransactionId): array
{
    return BookkeepingRecord::query()
        ->where('banking_transaction_id', $bankTransactionId->value)
        ->pluck('id')
        ->all();
}
```

### Step 4: Update MatchResult

**File**: `app/Domain/BankTransactions/MatchResult.php`

Add:

```php
public ?BankTransactionId $reversedByTransactionId = null,

// New factory:
public static function foundReversal(BankTransactionId $reversedById): self
{
    return new self(isMatch: true, reversedByTransactionId: $reversedById);
}
```

### Step 5: Add Reversal Matching in TransactionMatchingService

**File**: `app/Domain/BankTransactions/TransactionMatchingService.php`

Add:
```php
public function findReversalMatch(MatchCriteria $criteria): ?BankTransactionId;
```

**File**: `app/Domain/BankTransactions/TransactionMatchingServiceImpl.php`

Add dependency `BankTransactionRepository` (inject via constructor) and implement:

```php
#[Override]
public function findReversalMatch(MatchCriteria $criteria): ?BankTransactionId
{
    return $this->bankTransactionRepository->findReversalMatch($criteria);
}
```

Update `findMatch()` to try reversal matching as fallback:

```php
#[Override]
public function findMatch(MatchCriteria $criteria): MatchResult
{
    if ($criteria->amount > 0) {
        $result = $this->findMatchingInvoice($criteria);
        if ($result->isMatch) {
            return $result;
        }
    } else {
        $result = $this->findMatchingPurchaseOrder($criteria);
        if ($result->isMatch) {
            return $result;
        }
    }
    
    // Fallback: try reversal matching
    $reversedById = $this->findReversalMatch($criteria);
    if ($reversedById !== null) {
        return MatchResult::foundReversal($reversedById);
    }
    
    return MatchResult::none();
}
```

### Step 6: Update BankTransactionService

**File**: `app/Domain/BankTransactions/BankTransactionService.php`

Add:
```php
public function linkReversal(BankTransactionId $reversalId, BankTransactionId $originalId): void;
public function unlinkReversal(BankTransactionId $reversalId, BankTransactionId $originalId): void;
```

**File**: `app/Domain/BankTransactions/BankTransactionServiceImpl.php`

Update `resolveMatching()` to handle the new result type:

```php
#[Override]
public function resolveMatching(BankTransactionIdList $ids): void
{
    $criteriaList = $this->repository->getMatchCriteriaForIds($ids);
    
    foreach ($criteriaList as $bankTransactionId => $criteria) {
        $result = $this->matchingService->findMatch($criteria);
        $id = BankTransactionId::create($bankTransactionId);
        
        if (!$result->isMatch) {
            $this->repository->markAsUnresolvable($id);
            continue;
        }
        
        if ($result->invoiceId !== null) {
            $this->repository->attachInvoice($id, $result->invoiceId);
        }
        if ($result->purchaseOrderId !== null) {
            $this->repository->attachPurchaseOrder($id, $result->purchaseOrderId);
        }
        if ($result->reversedByTransactionId !== null) {
            $this->repository->linkReversal($id, $result->reversedByTransactionId);
            $this->repository->markAsResolved($result->reversedByTransactionId);
        }
        
        $this->repository->markAsResolved($id);
    }
}

#[Override]
public function linkReversal(BankTransactionId $reversalId, BankTransactionId $originalId): void
{
    $this->repository->linkReversal($reversalId, $originalId);

    // Mark the original's linked invoices and purchase orders as Declined
    $invoiceIds = $this->repository->getAttachedInvoiceIds($originalId);
    $this->invoiceService->markAsDeclined($invoiceIds);

    $purchaseOrderIds = $this->repository->getAttachedPurchaseOrderIds($originalId);
    $this->purchaseOrderService->markAsDeclined($purchaseOrderIds);
}

#[Override]
public function unlinkReversal(BankTransactionId $reversalId, BankTransactionId $originalId): void
{
    $this->repository->unlinkReversal($reversalId);
}
```

### Step 7: Add Declined Status to Purchase Orders

To support marking purchase orders as declined when their payment is reversed, we need to add `Declined` to the enum and service layer.

**File**: `app/Domain/PurchaseOrders/PurchaseOrderStatus.php`

Add:
```php
case Declined = 'declined';
```

**File**: `app/Domain/PurchaseOrders/PurchaseOrderRepository.php`

Add:
```php
public function markAsDeclined(PurchaseOrderIdList $ids): void;
```

**File**: `app/Infrastructure/PurchaseOrders/PurchaseOrderRepositoryDb.php`

Implement:
```php
#[Override]
public function markAsDeclined(PurchaseOrderIdList $ids): void
{
    PurchaseOrder::query()
        ->whereIn('id', array_map(static fn (PurchaseOrderId $id) => $id->value, $ids->ids))
        ->update(['status' => PurchaseOrderStatus::Declined]);
}
```

**File**: `app/Domain/PurchaseOrders/PurchaseOrderService.php`

Add:
```php
public function markAsDeclined(PurchaseOrderIdList $ids): void;
```

**File**: `app/Domain/PurchaseOrders/PurchaseOrderServiceImpl.php`

Add:
```php
#[Override]
public function markAsDeclined(PurchaseOrderIdList $ids): void
{
    $this->repository->markAsDeclined($ids);
}
```

### Step 8: Remove Unused Repository Method

**File**: `app/Domain/BankTransactions/BankTransactionRepository.php`

Remove `getAttachedBookkeepingRecordIds` — it's no longer needed since bookkeeping records are set to NULL rather than copied.

**File**: `app/Infrastructure/BankTransactions/BankTransactionDbRepository.php`

Remove the `getAttachedBookkeepingRecordIds` implementation.

### Step 9: Filament Actions for Manual Pairing

**File**: `app/Filament/Admin/Resources/BankingTransactions/Actions/LinkReversalAction.php` (NEW)

Create an action that:
- Is visible when the transaction is `Open` and not already part of a reversal
- Opens a modal with a select field to choose another unresolved transaction to link as reversal
- Filters candidate transactions: same account, opposite sign, similar absolute amount (±0.01), within 30 days
- On submit, calls `BankTransactionService::linkReversal()`

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankingTransactions\Actions;

use App\Domain\BankTransactions\BankTransactionId;
use App\Domain\BankTransactions\BankTransactionService;
use App\Domain\BankTransactions\BankTransactionStatus;
use App\Filament\Admin\Resources\BankingTransactions\Pages\ViewBankingTransaction;
use App\Models\BankingTransaction;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;

final class LinkReversalAction
{
    public static function make(): Action
    {
        return Action::make('linkReversal')
            ->label(__('labels.link_reversal'))
            ->icon('heroicon-o-link')
            ->color('warning')
            ->visible(static fn (BankingTransaction $record): bool =>
                $record->status === BankTransactionStatus::Open
                && !$record->isReversal()
                && !$record->isReversed()
            )
            ->form([
                Select::make('reversal_transaction_id')
                    ->label(__('labels.reversal_transaction'))
                    ->options(static function (BankingTransaction $record): array {
                        return BankingTransaction::query()
                            ->where('id', '!=', $record->id)
                            ->whereNull('reversed_by_transaction_id')
                            ->where('banking_account_number', $record->banking_account_number)
                            ->whereRaw('ABS(amount + ?) <= 0.01', [abs($record->amount)])
                            ->orderBy('date')
                            ->get()
                            ->mapWithKeys(fn (BankingTransaction $bt): array => [
                                $bt->id => sprintf(
                                    '#%d — %s — €%s',
                                    $bt->id,
                                    $bt->date->format('Y-m-d'),
                                    number_format($bt->amount, 2),
                                ),
                            ])
                            ->all();
                    })
                    ->searchable()
                    ->required(),
            ])
            ->action(static function (
                BankingTransaction $record,
                array $data,
                BankTransactionService $service,
                ViewBankingTransaction $livewire,
            ): void {
                $service->linkReversal(
                    BankTransactionId::create($record->id),
                    BankTransactionId::create((int) $data['reversal_transaction_id']),
                );
            })
            ->successNotificationTitle(__('labels.reversal_linked'))
            ->after(static fn (ViewBankingTransaction $livewire) => $livewire->dispatch('refresh'));
    }
}
```

**File**: `app/Filament/Admin/Resources/BankingTransactions/Actions/UnlinkReversalAction.php` (NEW)

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankingTransactions\Actions;

use App\Domain\BankTransactions\BankTransactionId;
use App\Domain\BankTransactions\BankTransactionService;
use App\Filament\Admin\Resources\BankingTransactions\Pages\ViewBankingTransaction;
use App\Models\BankingTransaction;
use Filament\Actions\Action;

final class UnlinkReversalAction
{
    public static function make(): Action
    {
        return Action::make('unlinkReversal')
            ->label(__('labels.unlink_reversal'))
            ->icon('heroicon-o-link-slash')
            ->color('danger')
            ->visible(static fn (BankingTransaction $record): bool =>
                $record->isReversal()
            )
            ->requiresConfirmation()
            ->action(static function (
                BankingTransaction $record,
                BankTransactionService $service,
                ViewBankingTransaction $livewire,
            ): void {
                $service->unlinkReversal(
                    BankTransactionId::create($record->id),
                    BankTransactionId::create($record->reversed_by_transaction_id),
                );
            })
            ->successNotificationTitle(__('labels.reversal_unlinked'))
            ->after(static fn (ViewBankingTransaction $livewire) => $livewire->dispatch('refresh'));
    }
}
```

### Step 10: Update View Page & Table

**File**: `app/Filament/Admin/Resources/BankingTransactions/Pages/ViewBankingTransaction.php`

Add new actions to `getHeaderActions()`:

```php
protected function getHeaderActions(): array
{
    return [
        RetryMatchingAction::make(),
        LinkReversalAction::make(),   // ← NEW
        UnlinkReversalAction::make(),  // ← NEW
        CompleteBankingTransactionAction::make(),
        EditAction::make(),
        DeleteAction::make(),
    ];
}
```

**File**: `app/Filament/Admin/Resources/BankingTransactions/Tables/BankingTransactionsTable.php`

Add a column to show reversal status and a filter for credit transactions:

Add import at top:
```php
use App\Enums\BooleanFilter;
```

Add column:
```php
TextColumn::make('reversedByTransaction')
    ->label(__('labels.reversal'))
    ->formatStateUsing(static fn (BankingTransaction $record): ?string =>
        $record->isReversal()
            ? __('labels.reversed_by', ['id' => $record->reversed_by_transaction_id])
            : ($record->isReversed()
                ? __('labels.has_reversal', ['id' => $record->reversedTransaction->id])
                : null
            )
    )
    ->color(static fn (BankingTransaction $record): string =>
        $record->isReversal() || $record->isReversed() ? 'danger' : 'gray'
    )
    ->url(static fn (BankingTransaction $record): ?string =>
        $record->isReversal()
            ? BankingTransactionResource::getUrl('view', ['record' => $record->reversed_by_transaction_id])
            : ($record->isReversed()
                ? BankingTransactionResource::getUrl('view', ['record' => $record->reversedTransaction->id])
                : null
            )
    )
    ->toggleable(),
```

Add `is_credit_transaction` filter to the `->filters()` array (alongside existing `book_year` filter):

```php
SelectFilter::make('is_credit_transaction')
    ->label(__('labels.credit_transaction'))
    ->options([
        '1' => __('labels.yes'),
        '0' => __('labels.no'),
    ])
    ->query(static function (Builder $query, array $state) {
        $value = $state['value'] ?? '';
        if ($value === '') {
            return $query;
        }

        return $query->where('is_credit_transaction', (bool) $value);
    }),
```

### Step 11: Update BankingTransactionStats Widget

**File**: `app/Filament/Admin/Resources/BankingTransactions/Widgets/BankingTransactionStats.php`

Add reversal info to the stats card:

```php
protected function getStats(): array
{
    if (!$this->record) {
        return [];
    }

    $matched = $this->record->matched_amount;
    $unmatched = $this->record->unmatched_amount;
    
    $stats = [
        StatsOverviewWidget\Stat::make('total', PriceFormatter::format($matched))
            ->label(__('labels.matched_transactions'))
            ->description('nog ' . PriceFormatter::format($unmatched) . ' ' . strtolower(__('labels.unmatched')))
            ->color($unmatched > 0 ? 'danger' : 'success'),
    ];
    
    if ($this->record->isReversal()) {
        $stats[] = StatsOverviewWidget\Stat::make('reversal', '↩')
            ->label(__('labels.is_reversal'))
            ->description(__('labels.reversed_by', ['id' => $this->record->reversed_by_transaction_id]))
            ->url(route('filament.resources.banking-transactions.view', ['record' => $this->record->reversed_by_transaction_id]))
            ->color('danger');
    }
    
    if ($this->record->isReversed()) {
        $stats[] = StatsOverviewWidget\Stat::make('reversed', '↪')
            ->label(__('labels.reversed'))
            ->description(__('labels.has_reversal', ['id' => $this->record->reversedTransaction->id]))
            ->url(route('filament.resources.banking-transactions.view', ['record' => $this->record->reversedTransaction->id]))
            ->color('danger');
    }
    
    return $stats;
}
```

### Step 12: Language Files

**File**: `lang/nl/labels.php`

Add:
```php
'link_reversal' => 'Koppel als storno',
'unlink_reversal' => 'Storno ontkoppelen',
'reversal_transaction' => 'Storno transactie',
'reversal_linked' => 'Storno gekoppeld',
'reversal_unlinked' => 'Storno ontkoppeld',
'is_reversal' => 'Is een storno',
'reversed_by' => 'Storno van transactie #:id',
'has_reversal' => 'Heeft storno #:id',
'reversed' => 'Gestorneerd',
'reversal' => 'Storno',
'credit_transaction' => 'Credittransactie',
'yes' => 'Ja',
'no' => 'Nee',
```

### Step 13: Factory Update

**File**: `database/factories/BankingTransactionFactory.php`

Add state:

```php
public function reversedBy(BankingTransaction $original): self
{
    return $this->state([
        'reversed_by_transaction_id' => $original->id,
        'is_credit_transaction' => true,
        'amount' => -$original->amount,
        'banking_account_number' => $original->banking_account_number,
    ]);
}
```

---

## Implementation Order

1. **Migration** — add `reversed_by_transaction_id` and `is_credit_transaction` columns
2. **PurchaseOrderStatus** — add `Declined` case
3. **PurchaseOrderRepository** — add `markAsDeclined` to interface + implementation
4. **PurchaseOrderService** — add `markAsDeclined` to interface + implementation
5. **BankingTransaction Model** — add relationships, casts, helpers, `isCreditTransaction()`, update `matchedAmount()`/`unmatchedAmount()` for credit transactions
6. **Language Files** — add Dutch labels (including `credit_transaction`, `yes`, `no`)
7. **BankTransactionRepository** — add `findReversalMatch`, `linkReversal`, `unlinkReversal` to interface (remove `getAttachedBookkeepingRecordIds`)
8. **BankTransactionDbRepository** — implement new methods (bulk insert for references, bookkeeping → NULL)
9. **MatchResult** — add `foundReversal()` factory and `reversedByTransactionId` property
10. **TransactionMatchingService** — add `findReversalMatch` to interface
11. **TransactionMatchingServiceImpl** — implement reversal matching, inject BankTransactionRepository
12. **BankTransactionService** — add `linkReversal`, `unlinkReversal` to interface
13. **BankTransactionServiceImpl** — implement new methods (includes decline logic), update `resolveMatching()`
14. **LinkReversalAction** (NEW) — Filament action for manual linking
15. **UnlinkReversalAction** (NEW) — Filament action for manual unlinking
16. **ViewBankingTransaction** — register new actions
17. **BankingTransactionsTable** — add reversal column and `is_credit_transaction` filter
18. **BankingTransactionStats** — add reversal info
19. **BankingTransactionFactory** — add `reversedBy()` state (with `is_credit_transaction = true`)
20. **Tests** — unit + feature

---

## Testing Plan

### Unit Tests (`tests/Unit/Domain/BankTransactions/`)

#### TransactionMatchingServiceImplTest (update existing)

Add:
1. **`test_find_match_falls_back_to_reversal_when_no_invoice_match()`**
   - Given a positive amount criteria with no invoice match
   - And a reversal match exists (opposite sign, same abs amount)
   - Returns `MatchResult::foundReversal()`

2. **`test_find_match_falls_back_to_reversal_when_no_purchase_order_match()`**
   - Given a negative amount criteria with no PO match
   - And a reversal match exists
   - Returns `MatchResult::foundReversal()`

3. **`test_find_match_returns_none_when_no_invoice_and_no_reversal()`**
   - No invoice match and no reversal match → `MatchResult::none()`

#### BankTransactionServiceImplTest (update existing)

Add:
4. **`test_resolve_matching_with_reversal_calls_link_reversal_and_mark_resolved()`**
   - MatchResult with reversedByTransactionId
   - Calls repository::linkReversal(), markAsResolved() for both
   - Verifies invoiceService::markAsDeclined() is called with the original's attached invoice IDs

5. **`test_link_reversal_marks_attached_invoices_as_declined()`**
   - Create transactions with an attached invoice
   - Call linkReversal()
   - Verify invoiceService::markAsDeclined() is called

6. **`test_link_reversal_marks_attached_purchase_orders_as_declined()`**
   - Create transactions with an attached purchase order
   - Call linkReversal()
   - Verify purchaseOrderService::markAsDeclined() is called

7. **`test_link_reversal_does_not_call_decline_when_no_attached_references()`**
   - Original has no attached invoices or POs
   - Calls markAsDeclined services zero times

8. **`test_unlink_reversal_delegates_to_repository()`**
   - Verify `unlinkReversal()` delegates to repository

### Feature Tests

#### BankingTransactionTest (update existing)

Add:
7. **`test_is_reversal_returns_true_when_reversed_by_transaction_id_is_set()`**
8. **`test_is_reversed_returns_true_when_another_transaction_points_to_it()`**
9. **`test_reversed_by_relationship_loads_original_transaction()`**
10. **`test_is_credit_transaction_returns_true_when_flag_is_true()`**
11. **`test_unmatched_amount_returns_zero_for_credit_transaction()`**
    - Create a credit transaction (is_credit_transaction = true, amount = 100)
    - Assert unmatched_amount === 0.0 even with no references attached
12. **`test_matched_amount_equals_amount_for_credit_transaction()`**
    - Create a credit transaction (is_credit_transaction = true, amount = -100)
    - Assert matched_amount === -100.0
13. **`test_unmatched_amount_still_works_normally_for_non_credit_transaction()`**
    - Regression: non-credit transactions still compute unmatched_amount correctly

#### BankTransactionDbRepositoryTest (update existing or create new)

Add:
18. **`test_find_reversal_match_returns_match_when_opposite_amount_exists()`**
19. **`test_find_reversal_match_returns_null_when_no_opposite_exists()`**
20. **`test_find_reversal_match_returns_null_when_amount_differs()`**
21. **`test_find_reversal_match_excludes_already_reversed_transactions()`**
22. **`test_link_reversal_sets_reversed_by_transaction_id_and_is_credit_transaction()`**
23. **`test_link_reversal_bulk_copies_invoice_references_from_original_to_reversal()`**
24. **`test_link_reversal_bulk_copies_purchase_order_references_from_original_to_reversal()`**
25. **`test_link_reversal_sets_bookkeeping_records_banking_transaction_id_to_null()`**
26. **`test_unlink_reversal_clears_reversed_by_transaction_id_and_is_credit_transaction()`**
27. **`test_unlink_reversal_removes_copied_references_from_reversal()`**

#### PurchaseOrderServiceImplTest (update existing or create new)

Add:
28. **`test_mark_as_declined_delegates_to_repository()`**
29. **`test_mark_as_declined_updates_purchase_order_status()`**

#### BankingTransactionResourceTest (update existing)

Add:
25. **`test_link_reversal_action_visible_for_unresolved_transaction()`**
26. **`test_link_reversal_action_not_visible_for_reversed_transaction()`**
27. **`test_unlink_reversal_action_visible_when_is_reversal()`**
28. **`test_unlink_reversal_action_not_visible_when_not_reversal()`**
29. **`test_is_credit_transaction_filter_shows_only_credit_transactions()`**

### Expectation Class Updates

#### BankTransactionRepositoryExpectation (update)

Add methods:
```php
public function expectsFindReversalMatch(MatchCriteria $criteria, ?BankTransactionId $return): void
public function expectsLinkReversal(BankTransactionId $reversalId, BankTransactionId $originalId): void
public function expectsUnlinkReversal(BankTransactionId $reversalId): void
```

#### BankTransactionServiceExpectation (update)

Add methods:
```php
public function expectsLinkReversal(BankTransactionId $reversalId, BankTransactionId $originalId): void
public function expectsUnlinkReversal(BankTransactionId $reversalId, BankTransactionId $originalId): void
```

#### InvoiceServiceExpectation (update)

Add method:
```php
public function expectsMarkAsDeclined(InvoiceIdList $ids): void
{
    $this->mock
        ->expects('markAsDeclined')
        ->with(equalTo($ids));
}
```

#### PurchaseOrderServiceExpectation (update)

Add method:
```php
public function expectsMarkAsDeclined(PurchaseOrderIdList $ids): void
{
    $this->mock
        ->expects('markAsDeclined')
        ->with(equalTo($ids));
}
```

---

## Edge Cases & Considerations

| Scenario | Handling |
|----------|----------|
| Reversal transaction arrives **before** original | The later one (by date) gets `reversed_by_transaction_id`. If same date, the higher ID is treated as the reversal. |
| Reversal amount is **slightly different** (e.g. €100.00 original, €99.99 reversal due to fees) | Within 0.01 tolerance: auto-match. Outside tolerance: won't auto-match. Admin can manually link. |
| Original was **already matched to an invoice** and completed | Invoice/PO references are copied to the reversal. The original's invoices and purchase orders are marked `Declined`. Bookkeeping records on the original are set to `banking_transaction_id = NULL`. Since `is_credit_transaction = true` on the reversal, `unmatched_amount` is always 0 so the reversal won't block anything. |
| A transaction is **reversed twice** | Prevented: `reversed_by_transaction_id` is a single FK. Once set, the transaction won't be available for another reversal match (filtered by `whereNull('reversed_by_transaction_id')`). |
| Manual linking of a pair that don't match exactly (non-auto criteria) | The manual action shows candidates within 0.01 tolerance by default, but admin can widen the criteria manually. |
| Deletion of the original transaction | `nullOnDelete()` — the reversal's `reversed_by_transaction_id` is set to null. `is_credit_transaction` must also be set to false to restore normal `unmatched_amount` behavior. |
| `unmatched_amount` for credit transactions | Always 0.0 regardless of references. The `CompleteTransactionAction` will see unmatched=0 and allow completion. |
| Reversal transaction appearing in reconciliation | Because `unmatched_amount = 0` and `matched_amount = amount`, the reversal appears as fully matched. It won't skew totals. |
| `unlinkReversal()` aftermath | Copied references are removed from the reversal. Invoices/POs stay `Declined` — unlinking doesn't auto-heal the decline (manual admin action required). Bookkeeping records remain `NULL`. |

---

## Files Changed Summary

| Action | File |
|--------|------|
| Create | `database/migrations/2026_08_04_000000_add_reversal_columns_to_banking_transactions.php` |
| Modify | `app/Domain/PurchaseOrders/PurchaseOrderStatus.php` |
| Modify | `app/Domain/PurchaseOrders/PurchaseOrderRepository.php` |
| Modify | `app/Infrastructure/PurchaseOrders/PurchaseOrderRepositoryDb.php` |
| Modify | `app/Domain/PurchaseOrders/PurchaseOrderService.php` |
| Modify | `app/Domain/PurchaseOrders/PurchaseOrderServiceImpl.php` |
| Modify | `app/Models/BankingTransaction.php` |
| Modify | `app/Domain/BankTransactions/BankTransactionRepository.php` |
| Modify | `app/Infrastructure/BankTransactions/BankTransactionDbRepository.php` |
| Modify | `app/Domain/BankTransactions/MatchResult.php` |
| Modify | `app/Domain/BankTransactions/TransactionMatchingService.php` |
| Modify | `app/Domain/BankTransactions/TransactionMatchingServiceImpl.php` |
| Modify | `app/Domain/BankTransactions/BankTransactionService.php` |
| Modify | `app/Domain/BankTransactions/BankTransactionServiceImpl.php` |
| Create | `app/Filament/Admin/Resources/BankingTransactions/Actions/LinkReversalAction.php` |
| Create | `app/Filament/Admin/Resources/BankingTransactions/Actions/UnlinkReversalAction.php` |
| Modify | `app/Filament/Admin/Resources/BankingTransactions/Pages/ViewBankingTransaction.php` |
| Modify | `app/Filament/Admin/Resources/BankingTransactions/Tables/BankingTransactionsTable.php` |
| Modify | `app/Filament/Admin/Resources/BankingTransactions/Widgets/BankingTransactionStats.php` |
| Modify | `lang/nl/labels.php` |
| Modify | `database/factories/BankingTransactionFactory.php` |
| Modify | `tests/Unit/Domain/BankTransactions/TransactionMatchingServiceImplTest.php` |
| Modify | `tests/Unit/Domain/BankTransactions/BankTransactionServiceImplTest.php` |
| Modify | `tests/Unit/Domain/BankTransactions/BankTransactionRepositoryExpectation.php` |
| Modify | `tests/Unit/Domain/BankTransactions/BankTransactionServiceExpectation.php` |
| Modify | `tests/Unit/Domain/Invoices/InvoiceServiceExpectation.php` |
| Modify | `tests/Unit/Domain/PurchaseOrders/PurchaseOrderServiceExpectation.php` |
| Modify | `tests/Feature/Models/BankingTransactionTest.php` |
| Create/Modify | `tests/Feature/Infrastructure/BankTransactions/BankTransactionDbRepositoryTest.php` |
| Modify | `tests/Feature/Filament/BankingTransaction/BankingTransactionResourceTest.php` |
| Create | `tests/Unit/Domain/BankTransactions/TransactionMatchingServiceExpectation.php` (if not exists) |

---

## References

- Current matching flow: `app/Domain/BankTransactions/TransactionMatchingServiceImpl.php` — positive→invoice, negative→PO
- Reversal pattern in invoices: `database/migrations/2026_08_01_000000_add_credit_invoice_id_to_invoices.php` — similar self-referencing FK approach
- Invoice model's `creditedInvoice()` / `creditInvoice()` relationships: `app/Models/Invoice.php` lines 43-47 — follow same naming convention
- Filament action pattern: `app/Filament/Admin/Resources/BankingTransactions/Actions/RetryMatchingAction.php` — use as template
- Test expectation pattern: `tests/Unit/Domain/BankTransactions/BankTransactionRepositoryExpectation.php` — add to existing
