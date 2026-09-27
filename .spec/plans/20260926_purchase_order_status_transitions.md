# Purchase Order Status Transitions Fix

**Date:** 2026-09-26

## Overview

The purchase order status workflow needs correction. The current implementation allows marking a PO as `Paid` manually with just a completeness check, but the correct flow should require linked transactions to be completed first.

## Current Situation

**Status Flow:**
```
Open → Pending → Paid
  ↓        ↓
Declined ←──────┘
```

**Current Transition Rules:**
- `Open` → `Pending`: Requires completeness (creditor name, IBAN, lines, cost centers)
- `Pending` → `Paid`: Requires completeness (wrong - should check linked transactions)
- `Open` → `Declined`: Always allowed with reason
- `Pending` → `Declined`: Always allowed with reason (already correct in policy)

**Problem:** The `markAsPaid` transition only checks if the PO is complete, but doesn't verify that linked transactions are completed.

## Expected Flow

1. PO is created with status `Open`
2. Administration approves it (`Pending`) - requires completeness check
   - Or administration declines it (`Declined`) - with reason
3. After approved (`Pending`), PO can be linked to transactions and bookkeeping records
4. When a linked transaction is `Completed`, PO becomes `Paid`
   - Or it can still be `Declined` if issues are found

## Planned Changes

### 1. Rename: `markAsPending` → `markAsApproved`

Rename all occurrences of `markAsPending` to `markAsApproved` for purchase orders:

**Files to update:**
- `app/Domain/PurchaseOrders/PurchaseOrderService.php` - interface method
- `app/Domain/PurchaseOrders/PurchaseOrderServiceImpl.php` - implementation method
- `app/Domain/PurchaseOrders/PurchaseOrderRepository.php` - interface method
- `app/Infrastructure/PurchaseOrders/PurchaseOrderRepositoryDb.php` - implementation method
- `app/Policies/PurchaseOrderPolicy.php` - policy method name
- `app/Filament/Admin/Resources/PurchaseOrders/Actions/PurchaseOrderStateActions.php` - action name and label reference
- `app/Domain/BankTransactions/BankTransactionServiceImpl.php` - call to `purchaseOrderService->markAsApproved`
- `lang/nl/labels.php` - rename key `mark_as_pending` to `mark_as_approved` with translation `'Markeer als goedgekeurd'`

### 2. Policy: Restrict `markAsPaid` transition and rename to `markAsApproved`

**File:** `app/Policies/PurchaseOrderPolicy.php`

The `markAsPaid` policy currently only checks if status is `Pending`. It should also verify that the PO has at least one linked transaction that is completed.

```php
public function markAsPaid(User $user, Model $purchaseOrder): bool
{
    Assert::isInstanceOf($purchaseOrder, PurchaseOrder::class);
    if ($purchaseOrder->status !== PurchaseOrderStatus::Pending) {
        return false;
    }

    // Check that at least one linked transaction is completed
    if ($purchaseOrder->bankTransactions()->where('status', BankTransactionStatus::Completed->value)->doesntExist()) {
        return false;
    }

    return $user->can('update_purchase_orders');
}
```

Add the import:
```php
use App\Domain\BankTransactions\BankTransactionStatus;
```

### 3. Policy: Restrict linking transactions to `Pending` POs

**File:** `app/Policies/PurchaseOrderPolicy.php`

Transactions should only be linkable to POs that are in `Pending` status (approved).

```php
public function attachTransaction(User $user, Model $purchaseOrder): bool
{
    Assert::isInstanceOf($purchaseOrder, PurchaseOrder::class);
    if ($purchaseOrder->status !== PurchaseOrderStatus::Pending) {
        return false;
    }

    return $user->can('update_purchase_orders');
}
```

### 4. Service: Add linked transaction validation for `markAsPaid`

**File:** `app/Domain/PurchaseOrders/PurchaseOrderServiceImpl.php`

The `markAsPaid` method should validate that at least one linked transaction is completed before allowing the transition.

```php
#[Override]
public function markAsPaid(PurchaseOrderIdList $ids): void
{
    // Validate that at least one linked transaction is completed for each PO
    foreach ($ids as $id) {
        $purchaseOrder = PurchaseOrder::findOrFail($id->value);
        $hasCompletedTransaction = $purchaseOrder
            ->bankTransactions()
            ->where('status', BankTransactionStatus::Completed->value)
            ->exists();

        if (!$hasCompletedTransaction) {
            throw new PurchaseOrderHasNoCompletedTransactionsException($id);
        }
    }

    $this->completenessService->findProblems($ids)->assertComplete();

    $this->repository->markAsPaid($ids);
    $this->bookkeepingRepository->createForPurchaseOrder($ids);
}
```

Add imports:
```php
use App\Domain\BankTransactions\BankTransactionStatus;
use App\Models\PurchaseOrder;
```

### 5. New Exception: No Completed Transactions

**File:** `app/Domain/PurchaseOrders/PurchaseOrderHasNoCompletedTransactionsException.php`

```php
<?php

declare(strict_types=1);

namespace App\Domain\PurchaseOrders;

final class PurchaseOrderHasNoCompletedTransactionsException extends \RuntimeException
{
    public function __construct(
        public readonly PurchaseOrderId $purchaseOrderId,
    ) {
        parent::__construct("Purchase order {$purchaseOrderId->value} has no completed linked transactions");
    }
}
```

### 6. Policy: Restrict `update` to `Open` POs (already correct)

**File:** `app/Policies/PurchaseOrderPolicy.php`

Currently only `Open` POs can be updated. This should remain the same - once approved (`Pending`), the PO cannot be modified.

### 7. UI: Update `markAsPaid` action visibility

**File:** `app/Filament/Admin/Resources/PurchaseOrders/Actions/PurchaseOrderStateActions.php`

The `markAsPaid` action should only be visible when there are linked completed transactions. The policy already handles this, but we can improve the UX by also checking in the UI.

```php
Action::make('markAsPaid')
    ->label(__('labels.mark_as_paid'))
    ->icon('heroicon-m-banknotes')
    ->color('success')
    ->requiresConfirmation()
    ->visible(static function (PurchaseOrder $record): bool => 
        auth()->user()->can('markAsPaid', $record) &&
        $record->bankTransactions()->where('status', BankTransactionStatus::Completed->value)->exists()
    )
```

Add the import:
```php
use App\Domain\BankTransactions\BankTransactionStatus;
```

### 8. Tests: Update existing tests

**Files to update:**
- `tests/Unit/Domain/PurchaseOrders/PurchaseOrderServiceImplTest.php` - rename `markAsPending` tests to `markAsApproved`
- `tests/Unit/Domain/PurchaseOrders/PurchaseOrderServiceTest.php` - rename tests
- `tests/Unit/Domain/PurchaseOrders/PurchaseOrderRepositoryExpectation.php` - rename expectation
- `tests/Feature/Filament/Admin/Resources/PurchaseOrderResourceTest.php` - rename action calls and add linked transaction tests

### 9. Migration: Add `completed_at` to purchase_orders (optional)

If we want to track when a PO was actually paid, add a `completed_at` timestamp that gets set when status changes to `Paid`.

## Summary of Changes

| File | Change |
|------|--------|
| `app/Domain/PurchaseOrders/PurchaseOrderService.php` | Rename `markAsPending` to `markAsApproved` |
| `app/Domain/PurchaseOrders/PurchaseOrderServiceImpl.php` | Rename method + add linked transaction validation |
| `app/Domain/PurchaseOrders/PurchaseOrderRepository.php` | Rename `markAsPending` to `markAsApproved` |
| `app/Infrastructure/PurchaseOrders/PurchaseOrderRepositoryDb.php` | Rename method |
| `app/Policies/PurchaseOrderPolicy.php` | Rename policy method + add linked transaction check |
| `app/Filament/Admin/Resources/PurchaseOrders/Actions/PurchaseOrderStateActions.php` | Rename action and label |
| `app/Domain/BankTransactions/BankTransactionServiceImpl.php` | Update method call |
| `lang/nl/labels.php` | Rename `mark_as_pending` to `mark_as_approved` |
| `app/Domain/PurchaseOrders/PurchaseOrderHasNoCompletedTransactionsException.php` | New exception class |
| `tests/Unit/Domain/PurchaseOrders/PurchaseOrderServiceImplTest.php` | Rename tests |
| `tests/Unit/Domain/PurchaseOrders/PurchaseOrderServiceTest.php` | Rename tests |
| `tests/Unit/Domain/PurchaseOrders/PurchaseOrderRepositoryExpectation.php` | Rename expectation |
| `tests/Feature/Filament/Admin/Resources/PurchaseOrderResourceTest.php` | Rename action calls |
