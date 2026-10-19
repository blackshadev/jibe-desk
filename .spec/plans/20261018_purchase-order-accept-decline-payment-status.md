    # Purchase order: separate accept/decline from payment status

Date: 2026-10-18

## Overview

Purchase orders currently use a single `PurchaseOrderStatus` (`open`, `pending`, `paid`, `declined`) that conflates two different concerns:

- whether the purchase order has been **accepted** for payment (approval), and
- where the money is in the **payment** lifecycle.

This plan splits those into two independent dimensions:

- **Approval state** (`approval_status`): `pending` → `accepted` / `declined`. Controlled by an explicit admin accept/decline flow (used to approve member-created declarations).
- **Payment status** (`status`): `open` → `pending` → `paid` / `declined` / `cancelled`. `open` = not yet sent to the bank, `pending` = sent to the bank (attached to a SEPA batch), `paid` = bank transaction imported and completed, `declined` = bank transaction declined, `cancelled` = approval was declined (PO voided).

The two dimensions move independently: an approval-declined PO becomes `cancelled` and can never be paid; a bank-declined PO keeps its `accepted` approval and can be reopened and re-sent.

> **Naming note:** `pending` exists on both dimensions. `PurchaseOrderStatus::Pending` means "sent to the bank / attached to a SEPA batch"; `PurchaseOrderApprovalStatus::Pending` means "awaiting approval". Always qualify which dimension is meant in code comments, labels, and tests.

## Current situation

- `App\Domain\Billing\PurchaseOrders\PurchaseOrderStatus` has cases `Open`, `Pending`, `Paid`, `Declined`.
- `PurchaseOrderServiceImpl::markAsApproved()` moves `Open → Pending` and creates bookkeeping records.
- `PurchaseOrderServiceImpl::markAsPaid()` moves `Pending → Paid` and creates bookkeeping records (requires a completed linked bank transaction).
- `PurchaseOrderServiceImpl::markAsDeclined()` moves any non-paid/declined PO to `Declined`.
- `PurchaseOrderRepositoryDb::findMatchingDebit()` matches `Open`/`Pending` POs.
- `SepaBatchRepositoryDb::addOpenPurchaseOrdersFromBatchMonth()` attaches `Pending` POs to a batch (status unchanged).
- `SepaBatchRepositoryDb::getPurchaseOrdersForExport()` exports `Pending` POs in a batch.
- `BankTransactionServiceImpl::linkReversal()` calls `markAsDeclined()` (payment decline); `unlinkReversal()` calls `markAsApproved()` (reopen).
- Editing/deleting is gated on `status === Open` in `PurchaseOrderPolicy`.

## Planned changes (summary)

1. Add `PurchaseOrderApprovalStatus` enum (`pending`, `accepted`, `declined`) and a matching `approval_status` column.
2. Keep `PurchaseOrderStatus::Pending` (its meaning shifts from "approved" to "sent to the bank"), add `Cancelled`. Keep `Open`, `Paid`, `Declined`.
3. Split service methods into **approval** (`acceptApproval`, `declineApproval`) and **payment** (`markAsPaymentPending`, `markAsPaid`, `markAsPaymentDeclined`, `reopenPayment`) operations.
4. Bookkeeping records are created on `markAsPaymentPending` and `markAsPaid` (mirroring `InvoiceServiceImpl`), not on `acceptApproval`.
5. `acceptApproval` carries the existing completeness gate; `declineApproval` sets payment status to `Cancelled`.
6. SEPA batch attach moves accepted+open POs to `pending` and creates bookkeeping; export reads `pending` POs.
7. Bank reversal uses `markAsPaymentDeclined` / `reopenPayment` (approval preserved).
8. Policy, Filament actions, table tabs, and labels updated for both dimensions.
9. Migration backfills `approval_status` only; `status` values are preserved. Old `pending` rows not attached to a SEPA batch return to `open` (they were approved, but not actually sent).

## Domain layer

### New enum `app/Domain/Billing/PurchaseOrders/PurchaseOrderApprovalStatus.php`

```php
enum PurchaseOrderApprovalStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';
}
```

### Updated enum `app/Domain/Billing/PurchaseOrders/PurchaseOrderStatus.php`

```php
enum PurchaseOrderStatus: string
{
    case Open = 'open';           // not yet sent to the bank
    case Pending = 'pending';     // sent to the bank (attached to a SEPA batch)
    case Paid = 'paid';
    case Declined = 'declined';   // bank declined the payment
    case Cancelled = 'cancelled'; // approval was declined
}
```

### Repository interface `app/Domain/Billing/PurchaseOrders/PurchaseOrderRepository.php`

```php
public function markAsApprovalAccepted(PurchaseOrderIdList $ids): void;   // approval: accept
public function markAsApprovalDeclined(PurchaseOrderIdList $ids, ?string $reason = null): void; // approval: decline
public function markAsPaymentPending(PurchaseOrderIdList $ids): void;     // payment: sent to bank
public function markAsPaid(PurchaseOrderIdList $ids): void;               // payment: confirmed
public function markAsPaymentDeclined(PurchaseOrderIdList $ids, ?string $reason = null): void; // payment: bank declined
public function reopenPayment(PurchaseOrderIdList $ids): void;            // payment: declined/cancelled -> open
public function hasCompletedTransactions(PurchaseOrderId $id): bool;
public function findMatchingDebit(...): ?PurchaseOrderId;
```

The old `markAsApproved` and `markAsDeclined` are removed in favour of the split names above. Every method now names the dimension it changes (`Approval` or `Payment`), so the repository reads unambiguously. `markAsDeclined` callers in `BankTransactionServiceImpl` switch to `markAsPaymentDeclined` / `reopenPayment`.

### Service interface `app/Domain/Billing/PurchaseOrders/PurchaseOrderService.php`

```php
public function acceptApproval(PurchaseOrderIdList $ids): void;          // gate + repository markAsApprovalAccepted
public function declineApproval(PurchaseOrderIdList $ids, ?string $reason = null): void; // repository markAsApprovalDeclined (-> cancelled)
public function markAsPaymentPending(PurchaseOrderIdList $ids): void; // gate + repository markAsPaymentPending + bookkeeping
public function markAsPaid(PurchaseOrderIdList $ids): void;      // gate + completed-tx check + repository markAsPaid + bookkeeping
public function markAsPaymentDeclined(PurchaseOrderIdList $ids, ?string $reason = null): void;
public function reopenPayment(PurchaseOrderIdList $ids): void;
```

### Service implementation `app/Domain/Billing/PurchaseOrders/PurchaseOrderServiceImpl.php`

```php
public function acceptApproval(PurchaseOrderIdList $ids): void
{
    $this->completenessService->findProblems($ids)->assertComplete();
    $this->repository->markAsApprovalAccepted($ids);
}

public function declineApproval(PurchaseOrderIdList $ids, ?string $reason = null): void
{
    $this->repository->markAsApprovalDeclined($ids, $reason);
}

public function markAsPaymentPending(PurchaseOrderIdList $ids): void
{
    $this->completenessService->findProblems($ids)->assertComplete();
    $this->repository->markAsPaymentPending($ids);
    $this->bookkeepingRepository->createForPurchaseOrder($ids);
}

public function markAsPaid(PurchaseOrderIdList $ids): void
{
    foreach ($ids->ids as $id) {
        if (! $this->repository->hasCompletedTransactions($id)) {
            throw new PurchaseOrderHasNoCompletedTransactionsException($id);
        }
    }
    $this->completenessService->findProblems($ids)->assertComplete();
    $this->repository->markAsPaid($ids);
    $this->bookkeepingRepository->createForPurchaseOrder($ids);
}

public function markAsPaymentDeclined(PurchaseOrderIdList $ids, ?string $reason = null): void
{
    $this->repository->markAsPaymentDeclined($ids, $reason);
}

public function reopenPayment(PurchaseOrderIdList $ids): void
{
    $this->repository->reopenPayment($ids);
}
```

Note: `acceptApproval` creates no bookkeeping; bookkeeping is created when the PO is actually sent to the bank (`markAsPaymentPending`).

## Infrastructure layer

### Migration

New migration (timestamp after the latest existing one):

```php
Schema::table('purchase_orders', static function (Blueprint $table): void {
    $table->string('approval_status')->index()->default('pending')->after('status');
});

// Backfill approval_status. Payment status is preserved, with one exception:
// old `pending` rows were approved but may not have reached a SEPA batch yet.
DB::table('purchase_orders')
    ->where('status', 'pending')
    ->whereNull('sepa_batch_id')
    ->update([
        'status' => 'open',
        'approval_status' => 'accepted',
    ]);

DB::table('purchase_orders')
    ->whereIn('status', ['pending', 'paid', 'declined'])
    ->update(['approval_status' => 'accepted']);

DB::table('purchase_orders')
    ->where('status', 'open')
    ->update(['approval_status' => 'pending']);
```

If the team prefers a pure data-preserving migration, the `whereNull('sepa_batch_id')` step can be dropped so old `pending` rows stay `pending` with approval `accepted`. Either way, no status values other than that optional reset are rewritten.

### Model `app/Models/PurchaseOrder.php`

Add `approval_status` to the docblock and casts:

```php
use App\Domain\Billing\PurchaseOrders\PurchaseOrderApprovalStatus;

#[Override]
protected function casts(): array
{
    return [
        'date' => 'datetime',
        'status' => PurchaseOrderStatus::class,
        'approval_status' => PurchaseOrderApprovalStatus::class,
        'amount' => 'decimal:3',
    ];
}
```

### Repository implementation `app/Infrastructure/Billing/PurchaseOrders/PurchaseOrderRepositoryDb.php`

```php
public function markAsApprovalAccepted(PurchaseOrderIdList $ids): void
{
    PurchaseOrder::query()
        ->whereIn('id', array_map(static fn (PurchaseOrderId $id) => $id->value, $ids->ids))
        ->update([
            'approval_status' => PurchaseOrderApprovalStatus::Accepted,
            'declined_reason' => null,
        ]);
}

public function markAsApprovalDeclined(PurchaseOrderIdList $ids, ?string $reason = null): void
{
    PurchaseOrder::query()
        ->whereIn('id', array_map(static fn (PurchaseOrderId $id) => $id->value, $ids->ids))
        ->update([
            'approval_status' => PurchaseOrderApprovalStatus::Declined,
            'status' => PurchaseOrderStatus::Cancelled,
            'declined_reason' => $reason,
        ]);
}

public function markAsPaymentPending(PurchaseOrderIdList $ids): void
{
    PurchaseOrder::query()
        ->whereIn('id', array_map(static fn (PurchaseOrderId $id) => $id->value, $ids->ids))
        ->update(['status' => PurchaseOrderStatus::Pending]);
}

public function markAsPaid(PurchaseOrderIdList $ids): void
{
    PurchaseOrder::query()
        ->whereIn('id', array_map(static fn (PurchaseOrderId $id) => $id->value, $ids->ids))
        ->update(['status' => PurchaseOrderStatus::Paid]);
}

public function markAsPaymentDeclined(PurchaseOrderIdList $ids, ?string $reason = null): void
{
    PurchaseOrder::query()
        ->whereIn('id', array_map(static fn (PurchaseOrderId $id) => $id->value, $ids->ids))
        ->update([
            'status' => PurchaseOrderStatus::Declined,
            'declined_reason' => $reason,
        ]);
}

public function reopenPayment(PurchaseOrderIdList $ids): void
{
    PurchaseOrder::query()
        ->whereIn('id', array_map(static fn (PurchaseOrderId $id) => $id->value, $ids->ids))
        ->update(['status' => PurchaseOrderStatus::Open]);
}

public function findMatchingDebit(...): ?PurchaseOrderId
{
    // match on open or pending (sent to bank) POs only
    $purchaseOrder = PurchaseOrder::query()
        ->whereIn('status', [PurchaseOrderStatus::Open, PurchaseOrderStatus::Pending])
        ...
}
```

### SEPA batch integration

`SepaBatchRepositoryDb::addOpenPurchaseOrdersFromBatchMonth()`:

```php
return PurchaseOrder::query()
    ->whereNull('sepa_batch_id')
    ->where('approval_status', PurchaseOrderApprovalStatus::Accepted)
    ->where('status', PurchaseOrderStatus::Open)
    ->whereBetween('date', [...])
    ->pluck('id')
    ->... // attach + flip to pending
```

Practical approach: fetch the IDs first, update `sepa_batch_id` + `status = pending`, return the IDs (or count). `SepaBatchServiceImpl::attachBatchMonthPurchaseOrders()` then calls `bookkeepingRepository->createForPurchaseOrder(PurchaseOrderIdList::fromArray($ids))` so bookkeeping is created on the transition to pending (mirroring invoice batch close).

`SepaBatchRepositoryDb::getPurchaseOrdersForExport()` filters `status = Pending` (unchanged filter, new meaning: sent to bank).

## Banking integration

`BankTransactionServiceImpl`:

```php
// linkReversal:
$purchaseOrderIds = ...;
$this->purchaseOrderService->markAsPaymentDeclined($purchaseOrderIds, null);

// unlinkReversal:
$purchaseOrderIds = ...;
$this->purchaseOrderService->markAsPaymentPending($purchaseOrderIds);
```

`BankTransactionServiceImpl::complete()` continues to call `purchaseOrderService->markAsPaid()` (already guarded).

## Policy `app/Policies/PurchaseOrderPolicy.php`

- `update` / `delete`: `status === Open` (unchanged, payment dimension).
- `acceptApproval`: approval `Pending` + status `Open` + permission.
- `declineApproval`: approval not `Declined` + status not `Paid`/`Cancelled`? + permission.
- `markAsPaymentPending`: approval `Accepted` + status `Open` + permission.
- `markAsPaid`: status `Pending` + completed transaction + permission.
- `attachBankTransaction`: status `Open` or `Pending` + permission.
- `markAsPaymentDeclined`: status `Pending` or `Paid` + permission.
- `reopenPayment`: status `Declined` or `Cancelled` + permission.

## Filament layer

### `PurchaseOrderStateActions`

Replace `markAsApproved` with `acceptApproval`; keep `markAsPaid`; keep `markAsDeclined` but repurpose as **payment decline** (status `declined`); add `declineApproval` (approval decline, status `cancelled`); add `markAsPaymentPending` and `reopenPayment` as needed. Each with completeness handling and notifications.

### List page / table

- Status column formats via new labels including `pending` (sent to bank) and `cancelled`.
- Provide tabs/filters for both approval state and payment status (user requested both).
- Row actions gated by policy.

### Labels `lang/nl/labels.php`

```php
'purchase_order_status' => [
    'open' => 'Open',
    'pending' => 'In behandeling', // sent to the bank
    'paid' => 'Betaald',
    'declined' => 'Geweigerd',
    'cancelled' => 'Geannuleerd',
],
'purchase_order_approval_status' => [
    'pending' => 'Wacht op goedkeuring',
    'accepted' => 'Goedgekeurd',
    'declined' => 'Afgewezen',
],
```

The existing `pending` label can stay unchanged (`In behandeling`) since it still describes a PO that is being processed by the bank. Approval `pending` needs its own label to avoid the collision.

And action labels `mark_as_approval_accepted`, `mark_as_approval_declined`, `mark_as_payment_pending`, `mark_as_payment_declined`, `reopen_payment`.

## Testing implications

- Existing `PurchaseOrderStatus` references to `Pending` stay valid; only their meaning changes. Re-check assertions that assumed `Pending` meant "approved".
- `PurchaseOrderServiceImplTest`: replace `markAsApproved` test with `acceptApproval` (no bookkeeping) and add `markAsPaymentPending` test (bookkeeping created); keep `markAsPaid`.
- `PurchaseOrderRepositoryDbMatchingTest`: keep matching `Open`/`Pending`; assert that a `pending` (sent to bank) PO is matched.
- Factory states: keep `pending()` (status `pending`, approval `accepted`); add `accepted()` (status `open`, approval `accepted`) and `cancelled()` (status `cancelled`, approval `declined`).
- Policy tests for new actions.
- Add a migration test covering: old `pending` without batch → `open` + accepted; old `pending` with batch → unchanged + accepted; old `paid`/`declined` → accepted; old `open` → approval pending.

## ADR

Consider recording an ADR under `.agents/docs/billing/` (or ADR-MAP) documenting the split of approval vs payment status for purchase orders. The term "pending" now exists on both dimensions (`PurchaseOrderStatus::Pending` = sent to the bank, `PurchaseOrderApprovalStatus::Pending` = awaiting approval), so the ubiquitous language needs an explicit disambiguation. Offer to create when the term changes land.
