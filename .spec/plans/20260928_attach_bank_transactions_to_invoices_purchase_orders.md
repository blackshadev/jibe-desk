# Attach Bank Transactions to Invoices and Purchase Orders

**Date:** 2026-09-28

**Current Situation:**
Bank transactions can already be attached to invoices and purchase orders from the BankTransaction edit view (via `InvoicesRelationManager` and `PurchaseOrdersRelationManager`). However, the inverse relation - attaching bank transactions from the Invoice or Purchase Order edit views - is not implemented. The relation managers `InvoiceBankTransactionsRelationManager` and `PurchaseOrderBankTransactionsRelationManager` exist but are read-only with no attach/detach actions.

**Planned Changes:**

This implementation adds the ability to attach and detach bank transactions from the Invoice and Purchase Order edit views, with appropriate policy checks and sorting by relevance (amount proximity, date proximity, and IBAN matching).

---

## Policy Updates

### BankTransactionPolicy - Add attachInvoices and attachPurchaseOrders methods

**File:** `app/Policies/BankTransactionPolicy.php`

Add two new policy methods that return `true` if the bank transaction is NOT completed:

```php
public function attachInvoices(User $user, Model $record): bool
{
    if ($record instanceof BankTransaction && $record->isCompleted()) {
        return false;
    }

    return $user->can('update_bank_transactions');
}

public function attachPurchaseOrders(User $user, Model $record): bool
{
    if ($record instanceof BankTransaction && $record->isCompleted()) {
        return false;
    }

    return $user->can('update_bank_transactions');
}
```

### InvoicePolicy - Add attachBankTransaction method

**File:** `app/Policies/InvoicePolicy.php`

Add a new policy method that returns `true` if the invoice is in `Pending` status (not yet completed):

```php
public function attachBankTransaction(User $user, Model $invoice): bool
{
    Assert::isInstanceOf($invoice, Invoice::class);
    if ($invoice->status !== InvoiceStatus::Pending) {
        return false;
    }

    return $user->can('update_invoices');
}
```

### PurchaseOrderPolicy - Add attachBankTransaction method

**File:** `app/Policies/PurchaseOrderPolicy.php`

The `attachTransaction` method already exists (line 69-77) but is named for attaching a purchase order TO a transaction. For consistency and clarity, rename the method to attachBankTransaction.

---

## BankTransaction Model Updates

**File:** `app/Models/BankTransaction.php`

Add two new scopes for filtering and sorting open transactions:

### Scope: notCompleted

```php
#[Scope]
protected function notCompleted(Builder $query): Builder
{
    return $query->where('status', BankTransactionStatus::Open);
}
```

### Scope: orderByRelevancy

This scope sorts by:
1. IBAN match (exact match ranks first)
2. Amount proximity (closest amount first)
3. Date proximity (closest date first)
4. ID as tiebreaker

```php
#[Scope]
protected function orderByRelevancy(Builder $query, float $targetAmount, ?string $iban = null, ?DateTimeInterface $targetDate = null): Builder
{
    $query = $query
        ->orderByRaw(
            'ABS(amount - ?) ASC',
            [$targetAmount],
        )->orderByRaw(
            $iban !== null
                ? 'CASE WHEN banking_account_number = ? THEN 0 ELSE 1 END ASC'
                : '1 ASC',
            $iban !== null ? [$iban] : [],
        );

    if ($targetDate !== null) {
        $query = $query->orderByRaw('ABS(DATEDIFF(date, ?)) ASC', [$targetDate->format('Y-m-d')]);
    }

    return $query->orderBy('id', 'asc');
}
```

---

## Relation Manager Updates

### InvoiceBankTransactionsRelationManager

**File:** `app/Filament/Admin/Resources/Invoices/RelationManagers/InvoiceBankTransactionsRelationManager.php`

Add header actions with `AttachBankTransactionAction` and record actions with `detach`:

```php
use App\Filament\Admin\Resources\BankTransactions\Actions\AttachBankTransactionAction;
use App\Filament\Admin\Resources\BankTransactions\Helpers\IsOpen;
use App\Domain\BankTransactions\BankTransactionId;
use App\Domain\BankTransactions\BankTransactionRepository;
use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Model;

protected function getHeaderActions(): array
{
    return [
        AttachBankTransactionAction::make(),
    ];
}

protected function getRecordActions(): array
{
    return [
        Action::make('detach')
            ->label(__('labels.detach'))
            ->color('danger')
            ->icon(Heroicon::XMark)
            ->requiresConfirmation()
            ->visible(
                static fn (RelationManager $livewire, BankTransaction $record): bool => (
                    IsOpen::checkInverse($livewire, $record)
                    && auth()->user()->can('attachBankTransaction', $livewire->getOwnerRecord())
                ),
            )
            ->action(static function (BankTransaction $record, RelationManager $livewire, BankTransactionRepository $repository): void {
                /** @var Invoice $invoice */
                $invoice = $livewire->getOwnerRecord();
                $repository->detachInvoice(
                    BankTransactionId::create($record->id),
                    InvoiceId::create($invoice->id),
                );
            })
            ->successNotificationTitle(__('labels.detached')),
    ];
}
```

### PurchaseOrderBankTransactionsRelationManager

**File:** `app/Filament/Admin/Resources/PurchaseOrders/RelationManagers/PurchaseOrderBankTransactionsRelationManager.php`

Similar updates with `AttachBankTransactionAction` and `detach` action:

```php
use App\Filament\Admin\Resources\BankTransactions\Actions\AttachBankTransactionAction;
use App\Filament\Admin\Resources\BankTransactions\Helpers\IsOpen;
use App\Domain\BankTransactions\BankTransactionId;
use App\Domain\BankTransactions\BankTransactionRepository;
use App\Domain\PurchaseOrders\PurchaseOrderId;
use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Model;

protected function getHeaderActions(): array
{
    return [
        AttachBankTransactionAction::make(),
    ];
}

protected function getRecordActions(): array
{
    return [
        Action::make('detach')
            ->label(__('labels.detach'))
            ->color('danger')
            ->icon(Heroicon::XMark)
            ->requiresConfirmation()
            ->visible(
                static fn (RelationManager $livewire, BankTransaction $record): bool => (
                    IsOpen::checkInverse($livewire, $record)
                    && auth()->user()->can('attachBankTransaction', $livewire->getOwnerRecord())
                ),
            )
            ->action(static function (BankTransaction $record, RelationManager $livewire, BankTransactionRepository $repository): void {
                /** @var PurchaseOrder $po */
                $po = $livewire->getOwnerRecord();
                $repository->detachPurchaseOrder(
                    BankTransactionId::create($record->id),
                    PurchaseOrderId::create($po->id),
                );
            })
            ->successNotificationTitle(__('labels.detached')),
    ];
}
```

---

## New Helper Class Update

**File:** `app/Filament/Admin/Resources/BankTransactions/Helpers/IsOpen.php`

Add a method for checking visibility from the inverse side (when the owner is the Invoice/PurchaseOrder and the record is the BankTransaction):

```php
public static function checkInverse(RelationManager $livewire, mixed $record): bool
{
    $ownerRecord = $livewire->getOwnerRecord();
    if ($ownerRecord instanceof BankTransaction) {
        return !$ownerRecord->isCompleted();
    }
    if ($record instanceof BankTransaction) {
        return !$record->isCompleted();
    }
    return true;
}
```

---

## New Action Classes

### AttachBankTransactionAction (Shared)

**New File:** `app/Filament/Admin/Resources/BankTransactions/Actions/AttachBankTransactionAction.php`

This is a shared action used by both Invoice and PurchaseOrder relation managers. It detects the owner type and routes accordingly:

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankTransactions\Actions;

use App\Domain\BankTransactions\BankTransactionId;
use App\Domain\BankTransactions\BankTransactionRepository;
use App\Domain\Invoices\InvoiceId;
use App\Domain\PurchaseOrders\PurchaseOrderId;
use App\Filament\Admin\Resources\BankTransactions\Helpers\IsOpen;
use App\Models\BankTransaction;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

final class AttachBankTransactionAction
{
    public static function make(): Action
    {
        return Action::make('attachBankTransaction')
            ->label(__('labels.attach_bank_transaction'))
            ->icon(Heroicon::BuildingLibrary)
            ->modalHeading(__('labels.attach_bank_transaction'))
            ->visible(
                static fn (RelationManager $livewire): bool => auth()->user()->can('attachBankTransaction', $livewire->getOwnerRecord()),
            )
            ->schema([
                Select::make('bank_transaction_id')
                    ->label(__('labels.bank_transaction'))
                    ->options(static function (RelationManager $livewire): Collection {
                        $ownerRecord = $livewire->getOwnerRecord();
                        
                        $targetAmount = $ownerRecord->total->toFloat();
                        if ($ownerRecord instanceof Invoice) {
                            $query
                                ->whereDoesntHave('invoices', static function ($query) use ($ownerRecord): void {
                                    $query->where('invoices.id', $ownerRecord->id);
                                })
                                ->orderByRelevancy($targetAmount, $ownerRecord->member?->iban, $ownerRecord->date);
                        } elseif ($ownerRecord instanceof PurchaseOrder) {
                             $query
                                ->whereDoesntHave('purchaseOrders', static function ($query) use ($ownerRecord): void {
                                    $query->where('purchase_orders.id', $ownerRecord->id);
                                })
                                ->orderByRelevancy($targetAmount, $ownerRecord->creditor_iban, $ownerRecord->date);
                        } else {
                            throw new RuntimeException('Unsupported owner record type: ' . get_class($ownerRecord));
                        }

                        return $query->get()
                            ->mapWithKeys(static fn (BankTransaction $bt): array => [
                                $bt->id => $bt->displayName,
                            ]);
                    })
                    ->searchable()
                    ->preload()
                    ->required(),
            ])
            ->action(static function (array $data, RelationManager $livewire, BankTransactionRepository $repository): void {
                $ownerRecord = $livewire->getOwnerRecord();

                if ($ownerRecord instanceof Invoice) {
                    $repository->attachInvoice(
                        BankTransactionId::create((int) $data['bank_transaction_id']),
                        InvoiceId::create($ownerRecord->id),
                    );
                } elseif ($ownerRecord instanceof PurchaseOrder) {
                    $repository->attachPurchaseOrder(
                        BankTransactionId::create((int) $data['bank_transaction_id']),
                        PurchaseOrderId::create($ownerRecord->id),
                    );
                }
            })
            ->successNotificationTitle(__('labels.attached'));
    }
}
```

Note: You may need to add a `displayName` attribute to `BankTransaction` if it doesn't exist:

**File:** `app/Models/BankTransaction.php` - add:

```php
/** @return Attribute<non-falsy-string, never> */
protected function displayName(): Attribute
{
    return Attribute::get(
        fn () => sprintf('[%s] %s - %s', $this->date->format('Y-m-d'), $this->description, number_format($this->amount, 2, ',', '.')),
    );
}
```

---

## Summary of Changes

| File | Change |
|------|--------|
| `app/Policies/BankTransactionPolicy.php` | Add `attachInvoices()` and `attachPurchaseOrders()` methods |
| `app/Policies/InvoicePolicy.php` | Add `attachBankTransaction()` method |
| `app/Policies/PurchaseOrderPolicy.php` | Add `attachBankTransaction()` method (alias) |
| `app/Models/BankTransaction.php` | Add `notCompleted()` and `orderByRelevancy()` scopes |
| `app/Filament/Admin/Resources/BankTransactions/Helpers/IsOpen.php` | Add `checkInverse()` method |
| `app/Filament/Admin/Resources/BankTransactions/Actions/AttachBankTransactionAction.php` | New action class |
| `app/Filament/Admin/Resources/Invoices/RelationManagers/InvoiceBankTransactionsRelationManager.php` | Add attach/detach actions |
| `app/Filament/Admin/Resources/PurchaseOrders/RelationManagers/PurchaseOrderBankTransactionsRelationManager.php` | Add attach/detach actions |
| `tests/Feature/Filament/Admin/Resources/InvoiceResourceTest.php` | Add policy tests for attachBankTransaction |
| `tests/Feature/Filament/Admin/Resources/PurchaseOrderResourceTest.php` | Add policy tests for attachBankTransaction |
| `tests/Feature/Models/BankTransactionTest.php` | Add tests for scopes |
| `tests/Feature/Policies/BankTransactionPolicyTest.php` | New test file for BankTransactionPolicy |

---

## Policy Logic Summary

| Policy | Method | Returns true when |
|--------|--------|-------------------|
| BankTransactionPolicy | `attachInvoices()` | Transaction is NOT completed AND user can update_bank_transactions |
| BankTransactionPolicy | `attachPurchaseOrders()` | Transaction is NOT completed AND user can update_bank_transactions |
| InvoicePolicy | `attachBankTransaction()` | Invoice status is Pending AND user can update_invoices |
| PurchaseOrderPolicy | `attachBankTransaction()` | PurchaseOrder status is Pending AND user can update_purchase_orders |

---

## Sorting Logic for Bank Transaction Selection

The `orderByRelevancy` scope on `BankTransaction` sorts by:
1. **IBAN match** - Transactions where `banking_account_number` matches the target IBAN are ranked first
2. **Amount proximity** - Sorted by `ABS(amount - targetAmount)` ascending
3. **Date proximity** (optional) - Sorted by `ABS(DATEDIFF(date, targetDate))` ascending
4. **ID** - As final tiebreaker, sorted by ID ascending

This matches the existing `orderByRelevancy` pattern used in `PurchaseOrder::orderByRelevancy()`.
