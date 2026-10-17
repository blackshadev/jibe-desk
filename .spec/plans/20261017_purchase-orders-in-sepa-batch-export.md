# Purchase Orders in the Sepa Batch SEPA Export

**Date:** 2026-10-17

## Overview

Add pending purchase orders to the existing SEPA batch export so the club can pay its creditors (suppliers) through the same monthly SEPA run it already uses to collect from members. Purchase orders are attached to a **Sepa batch** (the renamed invoice batch — see `20261017_rename-invoice-batch-to-sepa-batch.md`) and emitted as **SEPA Credit Transfer** (pain.001) entries in the batch's `sepa-credit-transfers.xml` file, alongside member refunds.

This plan assumes the rename plan has already landed: the batch concept is `SepaBatch` / `sepa_batches`, the typed ID is `SepaBatchId`, and the repository is `SepaBatchRepository`.

## Current situation

- The SEPA export lives in `SepaExportServiceImpl` (`app/Infrastructure/Invoices/SepaExportServiceImpl.php`), keyed by `SepaBatchId`. It builds two files: `sepa-direct-debits.xml` (pain.008, collect from members) and `sepa-credit-transfers.xml` (pain.001, pay out — currently only credit invoices with negative amounts).
- The credit-transfer XML is built inline with `digitick/sepa-xml` (`TransferFileFacadeFactory::createCustomerCredit(...)`), with the club as debtor (`SepaConfiguration` supplies name/IBAN/BIC).
- A `PurchaseOrder` (`app/Models/PurchaseOrder.php`) stores nullable `creditor_name` + `creditor_iban` (both mandatory before `Pending`), has no BIC, and its `total` is a `CompoundPrice` (`price` = net, `vat` = VAT amount) summed from `PurchaseOrderLine`s. `Pending` means "approved, awaiting payment"; `Paid` is reached only when a bank transaction matches.
- Purchase orders are currently matched to bank debits individually via `PurchaseOrderRepository::findMatchingDebit`; they have no batch concept and are never exported as SEPA.

## Summary of changes

Purchase orders gain a nullable `sepa_batch_id` column linking them to a Sepa batch. A new `SepaExportPurchaseOrder` DTO and `PurchaseOrderRepository::getForExport(SepaBatchId)` port let `SepaExportServiceImpl` read the pending purchase orders attached to a batch and emit one credit-transfer line per purchase order (`PO-{id}` reference, net amount, no BIC). A Filament "add purchase orders" action on the batch edit page attaches pending, un-batched purchase orders to the batch. The `PurchaseOrders → Invoicing` dependency is recorded as an ADR.

Decisions fixed before planning (do not re-litigate):

- **Placement:** folded into the existing Sepa batch export (credit-transfers file).
- **Amount:** net only (`price`), mirroring `SepaExportInvoice::amountInCents()`; `vat` stays bookkeeping-only.
- **Selection:** explicit attach — pending purchase orders are linked to a batch by the admin.
- **Reference:** `endToEndId` and remittance both `PO-{id}`.
- **Status effect:** none — attaching/exporting does not change the purchase order's status; it stays `Pending` until its bank debit matches.
- **Execution date / creditor config:** inherited from the batch (`sepa_transfer_date`) and `SepaConfiguration` (the existing credit-transfer block).
- **Bookkeeping:** none new (records are already created on approval).
- **Cross-context coupling:** `PurchaseOrders → Invoicing` via a published repository port + DTO (typed ID `SepaBatchId`), per ADR-0006.

---

## Database migration

Create `database/migrations/2026_10_17_000001_add_sepa_batch_id_to_purchase_orders_table.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::table('purchase_orders', static function (Blueprint $table): void {
            $table->foreignId('sepa_batch_id')->nullable()->constrained('sepa_batches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', static function (Blueprint $table): void {
            $table->dropConstrainedForeignId('sepa_batch_id');
        });
    }
};
```

---

## Model — `app/Models/PurchaseOrder.php`

Add the relationship and document the column:

```php
/** @return BelongsTo<SepaBatch, $this> */
public function sepaBatch(): BelongsTo
{
    return $this->belongsTo(SepaBatch::class);
}
```

Add `@property ?int $sepa_batch_id` to the class docblock. No `casts()` entry is needed (integer FK). The model uses `#[Guarded(['id', 'created_at', 'updated_at'])]`, so `sepa_batch_id` is mass-assignable already.

Symmetrically, add the inverse on `app/Models/SepaBatch.php` (mirroring its existing `invoices()`):

```php
/** @return HasMany<PurchaseOrder, $this> */
public function purchaseOrders(): HasMany
{
    return $this->hasMany(PurchaseOrder::class);
}
```

---

## Domain — Purchase Orders: DTO and repository port

### New value object `app/Domain/PurchaseOrders/SepaExportPurchaseOrder.php`

Mirrors `SepaExportInvoice`. `amountInCents()` uses `price` only (net), matching the decision.

```php
<?php

declare(strict_types=1);

namespace App\Domain\PurchaseOrders;

use App\Domain\SharedKernel\CompoundPrice;

final readonly class SepaExportPurchaseOrder
{
    public function __construct(
        public PurchaseOrderId $purchaseOrderId,
        public string $creditorName,
        public string $creditorIban,
        public CompoundPrice $total,
    ) {}

    public function reference(): string
    {
        return 'PO-' . $this->purchaseOrderId->value;
    }

    public function amountInCents(): int
    {
        return (int) round($this->total->price * 100);
    }
}
```

### Extend `app/Domain/PurchaseOrders/PurchaseOrderRepository.php`

Add one read method (the cross-context `SepaBatchId` import is a typed ID, allowed by ADR-0006 and the `DomainDependencyRule`):

```php
use App\Domain\Invoices\SepaBatchId;

#[Autowire]
interface PurchaseOrderRepository
{
    // ... existing methods ...

    /**
     * Load pending purchase orders attached to a Sepa batch, for credit-transfer export.
     *
     * @return list<SepaExportPurchaseOrder>
     */
    public function getForExport(SepaBatchId $batchId): array;
}
```

The attach is done from the Filament action directly (see below), mirroring the existing `addInvoices` action, so no `attachToBatch` domain method is required. If you prefer a domain-guarded attach, add `attachToBatch(SepaBatchId $batchId, PurchaseOrderIdList $ids): void` here and call it from the action instead.

---

## Infrastructure — Purchase Orders: repository implementation

### Extend `app/Infrastructure/PurchaseOrders/PurchaseOrderRepositoryDb.php`

Filter to purchase orders attached to the batch that are still `Pending` (so a declined or already-paid order is excluded), eager-load lines to avoid the `$po->total` N+1, and map to the DTO:

```php
use App\Domain\Invoices\SepaBatchId;
use App\Domain\PurchaseOrders\SepaExportPurchaseOrder;

#[Override]
public function getForExport(SepaBatchId $batchId): array
{
    return PurchaseOrder::query()
        ->where('sepa_batch_id', $batchId->value)
        ->where('status', PurchaseOrderStatus::Pending)
        ->with('lines')
        ->get()
        ->map(static fn (PurchaseOrder $purchaseOrder): SepaExportPurchaseOrder => new SepaExportPurchaseOrder(
            purchaseOrderId: PurchaseOrderId::create($purchaseOrder->id),
            creditorName: $purchaseOrder->creditor_name ?? '',
            creditorIban: $purchaseOrder->creditor_iban ?? '',
            total: $purchaseOrder->total,
        ))
        ->all();
}
```

---

## Infrastructure — Invoicing: emit purchase orders in the credit-transfer file

### Modify `app/Infrastructure/Invoices/SepaExportServiceImpl.php`

Inject the purchase-order repository and add a second loop that writes purchase orders into the existing credit-transfer block. The debtor (club) details and `dueDate` come from the batch's existing payment-info block, so no new payment-info block is needed.

Constructor:

```php
final readonly class SepaExportServiceImpl implements SepaExportService
{
    public function __construct(
        private SepaBatchRepository $batchRepository,
        private SepaConfiguration $configuration,
        private PurchaseOrderRepository $purchaseOrderRepository,
    ) {}
```

In `export()`, load the purchase orders after the invoices:

```php
$dueDate = $this->batchRepository->getSepaTransferDate($batchId);

/** @var list<SepaExportInvoice> $invoices */
$invoices = $this->batchRepository->getInvoicesForExport($batchId);

/** @var list<SepaExportPurchaseOrder> $purchaseOrders */
$purchaseOrders = $this->purchaseOrderRepository->getForExport($batchId);
```

After the existing invoice loop, add the purchase-order loop (inside the same `$hasCreditTransfer` tracking):

```php
foreach ($purchaseOrders as $purchaseOrder) {
    if ($purchaseOrder->amountInCents() === 0) {
        continue;
    }

    $hasCreditTransfer = true;
    $credit->addTransfer($paymentInfoId, [
        'amount' => $purchaseOrder->amountInCents(),
        'creditorIban' => $purchaseOrder->creditorIban,
        'creditorName' => $purchaseOrder->creditorName,
        'endToEndId' => $purchaseOrder->reference(),
        'remittanceInformation' => $purchaseOrder->reference(),
    ]);
}
```

No `creditorBic` key is passed for purchase orders (they have no BIC); `digitick/sepa-xml` omits the `<CdtrAgt>` element when BIC is absent. The `return new SepaExport(...)` at the end is unchanged — a batch with only purchase orders (no invoices) still yields a `creditTransfers` string and an empty `directDebit`.

---

## Filament — attach purchase orders to a batch

### Modify `app/Filament/Admin/Resources/SepaBatches/Pages/EditSepaBatch.php`

Add a header action mirroring `addInvoices`. It offers only `Pending`, un-batched purchase orders and sets `sepa_batch_id` on submit. Add the relevant imports (`PurchaseOrder`, `PurchaseOrderStatus`, `Select`).

```php
Action::make('addPurchaseOrders')
    ->label(__('labels.add_purchase_orders'))
    ->icon('heroicon-m-plus')
    ->schema([
        Select::make('purchase_order_ids')
            ->label(__('labels.purchase_orders'))
            ->multiple()
            ->searchable()
            ->options(
                static fn () => PurchaseOrder::query()
                    ->whereNull('sepa_batch_id')
                    ->where('status', PurchaseOrderStatus::Pending)
                    ->orderBy('date')
                    ->get()
                    ->mapWithKeys(static fn (PurchaseOrder $purchaseOrder) => [
                        $purchaseOrder->id => sprintf(
                            '[%s] %s — %s',
                            $purchaseOrder->date->format('Y-m-d'),
                            $purchaseOrder->description,
                            $purchaseOrder->total,
                        ),
                    ]),
            ),
    ])
    ->requiresConfirmation()
    ->action(static function (array $data, SepaBatch $record): void {
        PurchaseOrder::query()
            ->whereIn('id', $data['purchase_order_ids'])
            ->update(['sepa_batch_id' => $record->id]);
    })
    ->successNotificationTitle(__('notifications.purchase_orders_added_to_batch'))
    ->visible(OnOpenSepaBatch::make(...)),
```

`OnOpenSepaBatch` is the renamed `OnOpenInvoiceBatch` visibility helper, so the action is only available while the batch is `Open`.

Add a read-only relation manager to surface the attached purchase orders on the batch page (mirror `SepaBatchInvoicesRelationManager`), showing `date`, `description`, `creditor_name`, `creditor_iban`, `total`, and `status`:

```php
// app/Filament/Admin/Resources/SepaBatches/RelationManagers/SepaBatchPurchaseOrdersRelationManager.php
final class SepaBatchPurchaseOrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'purchaseOrders';

    protected static ?string $title = 'Inkooporders';
}
```

Register it in `EditSepaBatch::getRelationManagers()` alongside the invoices relation manager. No detach action initially.

---

## Translations

`lang/nl/labels.php` already has `purchase_order` / `purchase_orders` / `purchase_order_information`. Add:

```php
'add_purchase_orders' => 'Inkooporders toevoegen',
```

`lang/nl/notifications.php` add:

```php
'purchase_orders_added_to_batch' => 'Inkooporders toegevoegd aan batch',
```

---

## Documentation (domain model + ADR)

### New ADR — `/.agents/docs/ADR-MAP.md` (system-wide) or `/.agents/docs/purchase-orders/ADR-LIST.md`

Follow the existing ADR location. Title: "PurchaseOrders depends on Invoicing for the SEPA credit-transfer export". Record:

- Purchase orders are attached to a Sepa batch and read by `SepaExportServiceImpl` through the published `PurchaseOrderRepository::getForExport(SepaBatchId)` port returning a `SepaExportPurchaseOrder` DTO.
- This introduces a `PurchaseOrders → Invoicing` dependency (importing the `SepaBatchId` typed ID), consistent with ADR-0006 (typed IDs / DTOs / ports, not Eloquent models).
- The exported amount is `price` (net) only; `vat` remains bookkeeping-only; the creditor BIC is omitted.

### `/.agents/docs/purchase-orders/CONTEXT.md`

Add a `SEPAExport` term: a pain.001 credit-transfer entry built from `Pending` purchase orders attached to a Sepa batch, with the club as debtor; each entry uses `PO-{id}` as reference, omits the creditor BIC, and carries the net `price` amount.

### `/.agents/docs/GLOSSARY.md`

Expand the `SepaBatch` definition (renamed in the rename plan) to state it now groups invoice direct-debits **and** purchase-order credit transfers for a single SEPA export run.

### `/.agents/docs/CONTEXT-MAP.md`

Add the relationship `Purchase Orders → Invoicing`: Purchase Orders exposes a `SepaExportPurchaseOrder` read model via `PurchaseOrderRepository::getForExport`, consumed by Invoicing's SEPA export.

---

## Tests

Follow existing conventions: `Expectation` helper classes in `tests/Unit/Domain/...` / `tests/Feature/Infrastructure/...`, Mockery for ports, `Tests\FeatureTestCase`.

### Unit — extend `tests/Feature/Infrastructure/Invoices/SepaExportServiceImplTest.php`

Construct `SepaExportServiceImpl` with a mocked `PurchaseOrderRepository` (add an `Expectation` helper). Cover:

- Purchase orders attached to the batch are emitted into `creditTransfers` with `PO-{id}` in `endToEndId`/remittance, the creditor name/IBAN, and the net amount.
- A batch with only purchase orders (no invoices) yields non-empty `creditTransfers` and empty `directDebit`.
- Zero-amount purchase orders are skipped.
- Purchase orders do **not** add a `CdtrAgt` (BIC) element.

### Feature — `tests/Feature/Infrastructure/PurchaseOrders/PurchaseOrderRepositoryDbTest.php`

Cover `getForExport` against a real database:

- Returns only `Pending` purchase orders whose `sepa_batch_id` matches.
- Excludes `Declined` / `Paid` purchase orders attached to the same batch.
- Maps `creditor_name`, `creditor_iban`, and the net `total` into the DTO.

### Filament — extend `tests/Feature/Filament/SepaBatches/SepaBatchResourceTest.php`

- The `addPurchaseOrders` action is visible on an `Open` batch and hidden otherwise.
- Submitting the action sets `sepa_batch_id` on the selected purchase orders.

### Full suite

```bash
./Taskfile artisan test --compact
```
