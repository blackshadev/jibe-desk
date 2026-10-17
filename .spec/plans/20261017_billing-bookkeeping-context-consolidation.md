# Consolidate the finance contexts: Billing submodules & Banking into Bookkeeping

**Date:** 2026-10-17

## Overview

The domain layer currently has nine bounded contexts, three of which (`Billing`, `Invoicing`, `Purchase Orders`) sit on one admin navigation group and two of which (`Banking`, `Bookkeeping`) sit on another. The code is more fragmented than the way the association actually thinks about its finances, and it forces ports/DTOs between code that changes together.

This plan consolidates the finance contexts into two, grouped by submodule:

- **Billing** (`app/Domain/Billing`) absorbs `Invoicing` and `Purchase Orders` and splits the SEPA rail into its own submodule. Submodules: `BillableItems`, `Subscriptions`, `Invoices`, `Payment`, `PurchaseOrders`.
- **Bookkeeping** (`app/Domain/Bookkeeping`) absorbs `BankAccounts`, `BankStatements` and `BankTransactions` as the `Banking` submodule. Submodules: `CostCenters`, `BookkeepingRecords`, `Banking` (with `BankAccounts`, `BankStatements`, `BankTransactions` inside it).

It is a pure structural move: no business logic, database schema, Eloquent model, Filament resource or behaviour changes. The bulk of the effort is mechanical namespace rewriting plus a mirrored test move.

This supersedes ADR 0005 and is recorded as new ADR 0009.

## Current situation

`CONTEXT-MAP.md` lists nine contexts; the code namespaces are:

- `app/Domain/Billing` — billable item templates + the `BillableItemInstance` subscription engine + `BillingItemApplicators/`.
- `app/Domain/Invoices` — invoice documents **and** every SEPA class (`SepaBatch*`, `SepaExport*`, `SepaConfiguration`, `MandateId`, `PaymentInformationId`) plus their jobs, listener, event and mail.
- `app/Domain/PurchaseOrders` — purchase orders, including `SepaExportPurchaseOrder`.
- `app/Domain/BankAccounts`, `app/Domain/BankStatements`, `app/Domain/BankTransactions` — the Banking context, described in the docs as "one context spanning three modules".
- `app/Domain/Bookkeeping` — only four files: `CostCenterId`, `CostCenterBudgetRepository`, `CostCenterYearResult`, `BookkeepingRecordRepository`.

Relevant facts that shaped the plan:

- The Filament navigation already groups resources the way this plan groups code. `app/Filament/Admin/Navigation/NavigationGroup.php` has `Bookkeeping` (BankAccounts, BankStatements, BankTransactions, BookkeepingRecords, CostCenters, Inventory) and `Invoicing` (Invoices, PurchaseOrders, SepaBatches). There is no `Billing` group.
- `SepaExportPurchaseOrder` lives in `app/Domain/PurchaseOrders` but is constructed by `app/Infrastructure/Invoices/SepaBatchRepositoryDb.php` and returned by the `SepaBatchRepository` port — its true owner is the payment rail.
- `BookkeepingRecordRepository` already imports `BankTransactions`, `Invoices` and `PurchaseOrders`; `app/Domain/BankTransactions/*` already imports `Invoices`, `PurchaseOrders` and `Bookkeeping`. A Billing ⇄ Bookkeeping cycle already exists in code; consolidating only makes it explicit.
- `dev/phpstan/Collectors/UnitTestCollector.php` derives the required unit-test path mechanically from the source path (`app/Domain/X.php` → `tests/Unit/Domain/XTest.php`), so every domain move forces a mirrored test move. `FeatureTestCollector` only enforces this for `app/Http/Controllers`, `app/Observers` and `app/Infrastructure/Database`; `app/Infrastructure/**` tests are convention-only.
- `dev/phpstan/Rules/DomainDependencyRule.php` allows anything under `App\Domain\`, so nesting does not trip domain purity.
- `jeroen-g/autowire` crawls `app_path()` recursively, so nested directories are picked up automatically; only the cached map needs clearing after the move.
- Stranded/duplicate tests already exist under `tests/Unit/Domain/Invoices/` and `tests/Feature/Infrastructure/Invoices/` that actually test `App\Domain\Billing` / `App\Infrastructure\Billing` classes.

## Summary of changes

Decisions fixed before planning (do not re-litigate):

- **One bounded context per umbrella.** `Invoicing` and `Purchase Orders` stop being contexts; they become Billing submodules. ADR 0006 no longer applies *between* Billing submodules — they may import each other's concrete classes. The umbrella name stays **Billing**.
- **Physical moves cover** `app/Domain`, `app/Infrastructure`, their mirrored tests, and the domain docs. `app/Models` stays flat; `app/Filament/Admin/Resources` keeps its current layout.
- **`Payment` is the SEPA submodule name** (SEPA is the bank rail inside it). Full split: every SEPA/batch/mandate class, event, job and mail moves to `Payment`.
- **`BillableItemsViewRepository` goes to `Subscriptions`** — it reads `billable_item_instances` and answers "what does this member owe now".
- **Bookkeeping gets full symmetry**: `CostCenters/`, `BookkeepingRecords/`, `Banking/{BankAccounts,BankStatements,BankTransactions}/`.
- **Docs**: one new ADR (0009, supersedes 0005); rewrite `billing/CONTEXT.md`; delete `invoicing/`, `purchase-orders/`, `banking/`; fold Banking terms into `bookkeeping/CONTEXT.md`.
- **Navigation group `Invoicing` is renamed to `Billing`.**
- **Stranded tests are relocated and exact duplicates deleted.**

## Target namespace mapping

The mapping is authoritative: every FQCN on the left moves to the FQCN on the right, and every reference in the tree is rewritten accordingly. Class basenames are unique within their new namespace, so a longest-first string replacement of the old FQCN is safe.

### Domain — Billing

`app/Domain/Billing/BillableItems/` (new):

- `App\Domain\Billing\BillableItem` → `App\Domain\Billing\BillableItems\BillableItem`
- `App\Domain\Billing\BillableItemId` → `App\Domain\Billing\BillableItems\BillableItemId`
- `App\Domain\Billing\BillableItemIdList` → `App\Domain\Billing\BillableItems\BillableItemIdList`
- `App\Domain\Billing\BillableItemList` → `App\Domain\Billing\BillableItems\BillableItemList`
- `App\Domain\Billing\BillPeriod` → `App\Domain\Billing\BillableItems\BillPeriod`

`app/Domain/Billing/Subscriptions/` (new):

- `App\Domain\Billing\BillableItemInstanceId` → `App\Domain\Billing\Subscriptions\BillableItemInstanceId`
- `App\Domain\Billing\BillableItemInstanceRepository` → `App\Domain\Billing\Subscriptions\BillableItemInstanceRepository`
- `App\Domain\Billing\BillableItemsViewRepository` → `App\Domain\Billing\Subscriptions\BillableItemsViewRepository`
- `App\Domain\Billing\BillingItemApplicators\*` → `App\Domain\Billing\Subscriptions\BillingItemApplicators\*` (7 interfaces + 7 `Impl` classes)

`app/Domain/Billing/Invoices/` (from `app/Domain/Invoices`, everything except the Payment list below):

`AppliedInvoiceWithLineIds`, `ApplyInvoiceLines`, `InvoiceGenerator`, `InvoiceGeneratorImpl`, `InvoiceId`, `InvoiceIdList`, `InvoiceLineId`, `InvoiceMailData`, `InvoiceMailLine`, `InvoiceMailRepository`, `InvoiceNumber`, `InvoiceNumberGenerator`, `InvoiceNumberGeneratorImpl`, `InvoiceNumberRepository`, `InvoiceRepository`, `InvoiceService`, `InvoiceServiceImpl`, `InvoiceStatus`, `InvoiceTarget`, `NewInvoice`, `SendInvoiceEmailData`, `Jobs/GenerateInvoice`, `Jobs/SendInvoiceEmail`, `Listeners/QueueInvoiceEmails`, `Mails/InvoiceMail`.

`app/Domain/Billing/Payment/` (new — from `app/Domain/Invoices` unless noted):

`MandateId`, `PaymentInformationId`, `SepaBatch`, `SepaBatchEmailData`, `SepaBatchGenerator`, `SepaBatchGeneratorImpl`, `SepaBatchId`, `SepaBatchRepository`, `SepaBatchService`, `SepaBatchServiceImpl`, `SepaBatchStatus`, `SepaConfiguration`, `SepaExport`, `SepaExportInvoice`, `SepaExportService`, `Events/SepaBatchClosed`, `Jobs/FinishSepaBatchGeneration`, `Jobs/SendSepaBatchCreatedEmail`, `Mails/SepaBatchCreatedMail`, and `SepaExportPurchaseOrder` (from `app/Domain/PurchaseOrders`).

`app/Domain/Billing/PurchaseOrders/` (from `app/Domain/PurchaseOrders`, everything except `SepaExportPurchaseOrder`):

`PurchaseOrderCompleteness`, `PurchaseOrderCompletenessService`, `PurchaseOrderCompletenessServiceImpl`, `PurchaseOrderHasNoCompletedTransactionsException`, `PurchaseOrderId`, `PurchaseOrderIdList`, `PurchaseOrderIncompleteException`, `PurchaseOrderLineCompleteness`, `PurchaseOrderProblem`, `PurchaseOrderProblems`, `PurchaseOrderProblemType`, `PurchaseOrderRepository`, `PurchaseOrderService`, `PurchaseOrderServiceImpl`, `PurchaseOrderStatus`.

### Domain — Bookkeeping

- `App\Domain\BankAccounts\*` → `App\Domain\Bookkeeping\Banking\BankAccounts\*`
- `App\Domain\BankStatements\*` → `App\Domain\Bookkeeping\Banking\BankStatements\*`
- `App\Domain\BankTransactions\*` (including `Jobs/`) → `App\Domain\Bookkeeping\Banking\BankTransactions\*`
- `App\Domain\Bookkeeping\CostCenterId` → `App\Domain\Bookkeeping\CostCenters\CostCenterId`
- `App\Domain\Bookkeeping\CostCenterBudgetRepository` → `App\Domain\Bookkeeping\CostCenters\CostCenterBudgetRepository`
- `App\Domain\Bookkeeping\CostCenterYearResult` → `App\Domain\Bookkeeping\CostCenters\CostCenterYearResult`
- `App\Domain\Bookkeeping\BookkeepingRecordRepository` → `App\Domain\Bookkeeping\BookkeepingRecords\BookkeepingRecordRepository`

### Infrastructure (mirrors the domain)

- `App\Infrastructure\Billing\BillableItemDbInstanceRepository` → `App\Infrastructure\Billing\Subscriptions\BillableItemDbInstanceRepository`
- `App\Infrastructure\Billing\BillableItemsViewDbRepository` → `App\Infrastructure\Billing\Subscriptions\BillableItemsViewDbRepository`
- `App\Infrastructure\Billing\ExtraMembershipBillingItemDbRepository` → `App\Infrastructure\Members\ExtraMembershipBillingItemDbRepository` *(judgement call: it implements the Members-owned port `App\Domain\Members\ExtraMembershipBillingItemRepository`, so it belongs in `Infrastructure\Members`; as a result `Infrastructure\Billing\BillableItems\` has no adapter of its own)*
- `App\Infrastructure\Invoices\InvoiceMailRepositoryDb` → `App\Infrastructure\Billing\Invoices\InvoiceMailRepositoryDb`
- `App\Infrastructure\Invoices\InvoiceNumberDbRepository` → `App\Infrastructure\Billing\Invoices\InvoiceNumberDbRepository`
- `App\Infrastructure\Invoices\InvoiceRepositoryDb` → `App\Infrastructure\Billing\Invoices\InvoiceRepositoryDb`
- `App\Infrastructure\Invoices\SepaBatchRepositoryDb` → `App\Infrastructure\Billing\Payment\SepaBatchRepositoryDb`
- `App\Infrastructure\Invoices\SepaExportServiceImpl` → `App\Infrastructure\Billing\Payment\SepaExportServiceImpl`
- `App\Infrastructure\PurchaseOrders\PurchaseOrderRepositoryDb` → `App\Infrastructure\Billing\PurchaseOrders\PurchaseOrderRepositoryDb`
- `App\Infrastructure\BankAccounts\*` → `App\Infrastructure\Bookkeeping\Banking\BankAccounts\*`
- `App\Infrastructure\BankStatements\*` → `App\Infrastructure\Bookkeeping\Banking\BankStatements\*`
- `App\Infrastructure\BankTransactions\*` → `App\Infrastructure\Bookkeeping\Banking\BankTransactions\*`
- `App\Infrastructure\Bookkeeping\BookkeepingRecordDbRepository` → `App\Infrastructure\Bookkeeping\BookkeepingRecords\BookkeepingRecordDbRepository`
- `App\Infrastructure\Bookkeeping\CostCenterBudgetDbRepository` → `App\Infrastructure\Bookkeeping\CostCenters\CostCenterBudgetDbRepository`

### Tests (mirror the source path)

`Tests\Unit\Domain\<old>` → `Tests\Unit\Domain\<new>` and `Tests\Feature\Infrastructure\<old>` → `Tests\Feature\Infrastructure\<new>`, using the same split as the code:

- `tests/Unit/Domain/Invoices/**` splits between `tests/Unit/Domain/Billing/Invoices/**` and `tests/Unit/Domain/Billing/Payment/**`.
- `tests/Unit/Domain/PurchaseOrders/SepaExportPurchaseOrderTest.php` → `tests/Unit/Domain/Billing/Payment/SepaExportPurchaseOrderTest.php`; the rest → `tests/Unit/Domain/Billing/PurchaseOrders/**`.
- `tests/Feature/Infrastructure/Billing/BillableItemInstanceTest.php` → `tests/Feature/Infrastructure/Billing/Subscriptions/BillableItemInstanceTest.php`.
- `tests/Feature/Infrastructure/Invoices/{BillableItemDbInstanceRepositoryTest,BillableItemsViewDbRepositoryTest}.php` → `tests/Feature/Infrastructure/Billing/Subscriptions/`.
- `tests/Feature/Infrastructure/Invoices/{SepaBatchRepositoryDbTest,SepaBatchRepositoryExpectation,SepaExportServiceImplTest}.php` → `tests/Feature/Infrastructure/Billing/Payment/`.
- `tests/Feature/Infrastructure/Invoices/ExtraMembershipBillingItemDbRepositoryTest.php` → `tests/Feature/Infrastructure/Members/`.
- `tests/Feature/Infrastructure/Invoices/**` (remaining invoice tests) and `tests/Feature/Infrastructure/PurchaseOrders/**` → their `Billing/Invoices`, `Billing/PurchaseOrders` mirrors.
- `tests/Feature/Infrastructure/BankTransactions/**` → `tests/Feature/Infrastructure/Bookkeeping/Banking/BankTransactions/**`.
- `tests/Feature/Infrastructure/Bookkeeping/**` splits into `Bookkeeping/CostCenters/**` and `Bookkeeping/BookkeepingRecords/**`.

## Moving the code

Move directories with `git mv` so history survives. Move the whole former directory first, then pull the submodule-specific files out.

```bash
# --- Billing: BillableItems + Subscriptions out of the old Billing ---
mkdir -p app/Domain/Billing/BillableItems app/Domain/Billing/Subscriptions
git mv app/Domain/Billing/BillableItem.php          app/Domain/Billing/BillableItems/
git mv app/Domain/Billing/BillableItemId.php        app/Domain/Billing/BillableItems/
git mv app/Domain/Billing/BillableItemIdList.php    app/Domain/Billing/BillableItems/
git mv app/Domain/Billing/BillableItemList.php      app/Domain/Billing/BillableItems/
git mv app/Domain/Billing/BillPeriod.php            app/Domain/Billing/BillableItems/
git mv app/Domain/Billing/BillableItemInstanceId.php         app/Domain/Billing/Subscriptions/
git mv app/Domain/Billing/BillableItemInstanceRepository.php app/Domain/Billing/Subscriptions/
git mv app/Domain/Billing/BillableItemsViewRepository.php    app/Domain/Billing/Subscriptions/
git mv app/Domain/Billing/BillingItemApplicators             app/Domain/Billing/Subscriptions/

# --- Billing: move Invoices wholesale, then split Payment out ---
git mv app/Domain/Invoices app/Domain/Billing/Invoices
mkdir -p app/Domain/Billing/Payment/Events app/Domain/Billing/Payment/Jobs app/Domain/Billing/Payment/Mails

# --- Billing: Purchase Orders wholesale, minus SepaExportPurchaseOrder ---
git mv app/Domain/PurchaseOrders/SepaExportPurchaseOrder.php app/Domain/Billing/Payment/SepaExportPurchaseOrder.php
git mv app/Domain/PurchaseOrders app/Domain/Billing/PurchaseOrders

# --- Billing: pull Payment files out of what is now Billing/Invoices ---
git mv app/Domain/Billing/Invoices/MandateId.php              app/Domain/Billing/Payment/
git mv app/Domain/Billing/Invoices/PaymentInformationId.php   app/Domain/Billing/Payment/
git mv app/Domain/Billing/Invoices/SepaBatch.php              app/Domain/Billing/Payment/
git mv app/Domain/Billing/Invoices/SepaBatchEmailData.php     app/Domain/Billing/Payment/
git mv app/Domain/Billing/Invoices/SepaBatchGenerator.php     app/Domain/Billing/Payment/
git mv app/Domain/Billing/Invoices/SepaBatchGeneratorImpl.php app/Domain/Billing/Payment/
git mv app/Domain/Billing/Invoices/SepaBatchId.php            app/Domain/Billing/Payment/
git mv app/Domain/Billing/Invoices/SepaBatchRepository.php    app/Domain/Billing/Payment/
git mv app/Domain/Billing/Invoices/SepaBatchService.php       app/Domain/Billing/Payment/
git mv app/Domain/Billing/Invoices/SepaBatchServiceImpl.php   app/Domain/Billing/Payment/
git mv app/Domain/Billing/Invoices/SepaBatchStatus.php        app/Domain/Billing/Payment/
git mv app/Domain/Billing/Invoices/SepaConfiguration.php      app/Domain/Billing/Payment/
git mv app/Domain/Billing/Invoices/SepaExport.php             app/Domain/Billing/Payment/
git mv app/Domain/Billing/Invoices/SepaExportInvoice.php      app/Domain/Billing/Payment/
git mv app/Domain/Billing/Invoices/SepaExportService.php      app/Domain/Billing/Payment/
git mv app/Domain/Billing/Invoices/Events/SepaBatchClosed.php         app/Domain/Billing/Payment/Events/
git mv app/Domain/Billing/Invoices/Jobs/FinishSepaBatchGeneration.php app/Domain/Billing/Payment/Jobs/
git mv app/Domain/Billing/Invoices/Jobs/SendSepaBatchCreatedEmail.php app/Domain/Billing/Payment/Jobs/
git mv app/Domain/Billing/Invoices/Mails/SepaBatchCreatedMail.php     app/Domain/Billing/Payment/Mails/

# --- Bookkeeping: Banking submodule ---
mkdir -p app/Domain/Bookkeeping/Banking
git mv app/Domain/BankAccounts     app/Domain/Bookkeeping/Banking/BankAccounts
git mv app/Domain/BankStatements   app/Domain/Bookkeeping/Banking/BankStatements
git mv app/Domain/BankTransactions app/Domain/Bookkeeping/Banking/BankTransactions

# --- Bookkeeping: CostCenters + BookkeepingRecords submodules ---
mkdir -p app/Domain/Bookkeeping/CostCenters app/Domain/Bookkeeping/BookkeepingRecords
git mv app/Domain/Bookkeeping/CostCenterId.php               app/Domain/Bookkeeping/CostCenters/
git mv app/Domain/Bookkeeping/CostCenterBudgetRepository.php app/Domain/Bookkeeping/CostCenters/
git mv app/Domain/Bookkeeping/CostCenterYearResult.php       app/Domain/Bookkeeping/CostCenters/
git mv app/Domain/Bookkeeping/BookkeepingRecordRepository.php app/Domain/Bookkeeping/BookkeepingRecords/
```

Mirror every move under `app/Infrastructure`, `tests/Unit/Domain` and `tests/Feature/Infrastructure` using the tables above, e.g.:

```bash
git mv app/Infrastructure/Invoices app/Infrastructure/Billing/Invoices
mkdir -p app/Infrastructure/Billing/Payment
git mv app/Infrastructure/Billing/Invoices/SepaBatchRepositoryDb.php app/Infrastructure/Billing/Payment/
git mv app/Infrastructure/Billing/Invoices/SepaExportServiceImpl.php  app/Infrastructure/Billing/Payment/

git mv app/Infrastructure/Billing/BillableItemDbInstanceRepository.php  app/Infrastructure/Billing/Subscriptions/
git mv app/Infrastructure/Billing/BillableItemsViewDbRepository.php     app/Infrastructure/Billing/Subscriptions/
git mv app/Infrastructure/Billing/ExtraMembershipBillingItemDbRepository.php app/Infrastructure/Members/

git mv app/Infrastructure/PurchaseOrders app/Infrastructure/Billing/PurchaseOrders

mkdir -p app/Infrastructure/Bookkeeping/Banking app/Infrastructure/Bookkeeping/CostCenters app/Infrastructure/Bookkeeping/BookkeepingRecords
git mv app/Infrastructure/BankAccounts     app/Infrastructure/Bookkeeping/Banking/BankAccounts
git mv app/Infrastructure/BankStatements   app/Infrastructure/Bookkeeping/Banking/BankStatements
git mv app/Infrastructure/BankTransactions app/Infrastructure/Bookkeeping/Banking/BankTransactions
git mv app/Infrastructure/Bookkeeping/BookkeepingRecordDbRepository.php app/Infrastructure/Bookkeeping/BookkeepingRecords/
git mv app/Infrastructure/Bookkeeping/CostCenterBudgetDbRepository.php  app/Infrastructure/Bookkeeping/CostCenters/
```

Then move the test tree the same way. `tests/Unit/Domain/Billing` already holds the pure-Billing tests, so create the submodules and move files into them rather than moving the directory (a directory cannot be moved into itself):

```bash
# --- Unit: Billing -> BillableItems + Subscriptions ---
mkdir -p tests/Unit/Domain/Billing/BillableItems tests/Unit/Domain/Billing/Subscriptions
for f in BillableItemIdTest BillableItemIdListTest BillableItemListTest BillableItemTest; do
    git mv "tests/Unit/Domain/Billing/$f.php" tests/Unit/Domain/Billing/BillableItems/
done
git mv tests/Unit/Domain/Billing/BillableItemInstanceIdTest.php           tests/Unit/Domain/Billing/Subscriptions/
git mv tests/Unit/Domain/Billing/BillableItemInstanceRepositoryExpectation.php tests/Unit/Domain/Billing/Subscriptions/
git mv tests/Unit/Domain/Billing/BillingItemApplicators                   tests/Unit/Domain/Billing/Subscriptions/

# --- Unit: Invoices -> Billing/Invoices, then split Payment out ---
git mv tests/Unit/Domain/Invoices tests/Unit/Domain/Billing/Invoices
mkdir -p tests/Unit/Domain/Billing/Payment/Events tests/Unit/Domain/Billing/Payment/Jobs tests/Unit/Domain/Billing/Payment/Mails
for f in MandateIdTest PaymentInformationIdTest SepaBatchEmailDataTest SepaBatchGeneratorImplTest \
         SepaBatchIdTest SepaBatchRepositoryExpectation SepaBatchServiceExpectation SepaBatchServiceImplTest \
         SepaBatchServiceTest SepaBatchTest SepaConfigurationTest SepaExportInvoiceTest SepaExportTest; do
    git mv "tests/Unit/Domain/Billing/Invoices/$f.php" tests/Unit/Domain/Billing/Payment/
done
git mv tests/Unit/Domain/Billing/Invoices/Events/SepaBatchClosedTest.php        tests/Unit/Domain/Billing/Payment/Events/
git mv tests/Unit/Domain/Billing/Invoices/Jobs/FinishSepaBatchGenerationTest.php tests/Unit/Domain/Billing/Payment/Jobs/
git mv tests/Unit/Domain/Billing/Invoices/Jobs/SendSepaBatchCreatedEmailTest.php tests/Unit/Domain/Billing/Payment/Jobs/
git mv tests/Unit/Domain/Billing/Invoices/Mails/SepaBatchCreatedMailTest.php     tests/Unit/Domain/Billing/Payment/Mails/

# --- Unit: Purchase Orders -> Billing/PurchaseOrders, SepaExportPurchaseOrderTest -> Payment ---
git mv tests/Unit/Domain/PurchaseOrders tests/Unit/Domain/Billing/PurchaseOrders
git mv tests/Unit/Domain/Billing/PurchaseOrders/SepaExportPurchaseOrderTest.php tests/Unit/Domain/Billing/Payment/

# --- Unit: Banking -> Bookkeeping/Banking ---
mkdir -p tests/Unit/Domain/Bookkeeping/Banking
git mv tests/Unit/Domain/BankAccounts     tests/Unit/Domain/Bookkeeping/Banking/BankAccounts
git mv tests/Unit/Domain/BankStatements   tests/Unit/Domain/Bookkeeping/Banking/BankStatements
git mv tests/Unit/Domain/BankTransactions tests/Unit/Domain/Bookkeeping/Banking/BankTransactions

# --- Unit: Bookkeeping -> CostCenters + BookkeepingRecords ---
mkdir -p tests/Unit/Domain/Bookkeeping/CostCenters tests/Unit/Domain/Bookkeeping/BookkeepingRecords
git mv tests/Unit/Domain/Bookkeeping/CostCenterIdTest.php               tests/Unit/Domain/Bookkeeping/CostCenters/
git mv tests/Unit/Domain/Bookkeeping/CostCenterYearResultTest.php       tests/Unit/Domain/Bookkeeping/CostCenters/
git mv tests/Unit/Domain/Bookkeeping/BookkeepingRecordRepositoryExpectation.php tests/Unit/Domain/Bookkeeping/BookkeepingRecords/
```

Feature tests mirror the infrastructure moves one-for-one (`tests/Feature/Infrastructure/Invoices` splits between `Billing/Invoices` and `Billing/Payment`, plus `Billing/Subscriptions` and `Members`; `tests/Feature/Infrastructure/Billing/BillableItemInstanceTest.php` → `Billing/Subscriptions/`; `tests/Feature/Infrastructure/PurchaseOrders` → `Billing/PurchaseOrders`; `tests/Feature/Infrastructure/BankTransactions` → `Bookkeeping/Banking/BankTransactions`; `tests/Feature/Infrastructure/Bookkeeping` splits between `Bookkeeping/CostCenters` and `Bookkeeping/BookkeepingRecords`).

## Rewriting namespaces and imports

Because `App\Domain\Invoices` splits between two submodules, a blanket prefix `sed` is wrong. Derive the exact old→new FQCN map from the renames `git mv` produced, then apply longest-first across every PHP file. Run this **immediately after the `git mv` pass and before any content edits**, while the moves are still pure renames:

```python
import re, subprocess
from pathlib import Path

# git mv stages renames, so --cached --diff-filter=R yields exact old/new path pairs.
raw = subprocess.run(
    ["git", "diff", "--cached", "--name-status", "-M", "-z", "--diff-filter=R"],
    capture_output=True, text=True, check=True,
).stdout.split("\0")

tokens = [token for token in raw if token]
pairs = [(tokens[i + 1], tokens[i + 2]) for i in range(0, len(tokens), 3)]

def fqcn(path: str) -> str:
    parts = Path(path).with_suffix("").parts
    if parts[0] == "app":
        return "App\\" + "\\".join(parts[1:])
    if parts[0] == "tests":
        return "Tests\\" + "\\".join(parts[1:])
    return ""

mapping = {fqcn(old): fqcn(new) for old, new in pairs if old.endswith(".php") and fqcn(old)}
moved = set(mapping.values())

files = [p for folder in ("app", "tests", "database", "routes", "lang") for p in Path(folder).rglob("*.php")]

for path in files:
    text = original = path.read_text()
    target_namespace = fqcn(str(path))
    # Only rewrite the namespace of files that actually moved (their FQCN is a map value);
    # every other file keeps its namespace.
    if target_namespace in moved:
        text = re.sub(r"^namespace [^;]+;", f"namespace {target_namespace};", text, count=1, flags=re.M)
    for old, new in sorted(mapping.items(), key=lambda item: -len(item[0])):
        text = text.replace(old, new)
    if text != original:
        path.write_text(text)
```

This rewrites (a) each moved file's own `namespace` line from its new path and (b) every `use`, fully-qualified inline reference and `::class` string via the FQCN map.

Representative before → after for a moved domain file:

```php
// before — app/Domain/Invoices/InvoiceTarget.php
namespace App\Domain\Invoices;

use App\Domain\Members\MemberId;
use DateTimeInterface;

final readonly class InvoiceTarget
{
    public function __construct(
        public MemberId $memberId,
        public DateTimeInterface $invoiceDate,
        public ?SepaBatchId $batchId = null,   // same-context class
    ) {}
}
```

```php
// after — app/Domain/Billing/Invoices/InvoiceTarget.php
namespace App\Domain\Billing\Invoices;

use App\Domain\Billing\Payment\SepaBatchId;   // sibling submodule, allowed now
use App\Domain\Members\MemberId;
use DateTimeInterface;

final readonly class InvoiceTarget
{
    public function __construct(
        public MemberId $memberId,
        public DateTimeInterface $invoiceDate,
        public ?SepaBatchId $batchId = null,
    ) {}
}
```

Representative before → after for an outside call site (import-only, filename unchanged):

```php
// app/Console/Commands/GenerateSepaBatchCommand.php
-use App\Domain\Invoices\SepaBatch;
-use App\Domain\Invoices\SepaBatchGenerator;
+use App\Domain\Billing\Payment\SepaBatch;
+use App\Domain\Billing\Payment\SepaBatchGenerator;
```

### Call sites outside `app/Domain` and `app/Infrastructure`

These files keep their names and only need import updates (handled by the script, listed here so the reviewer can check them):

- `app/Models/Invoice.php`, `app/Models/SepaBatch.php`, `app/Models/BillableItem.php`, `app/Models/BankStatement.php`, `app/Models/BankTransaction.php`
- `app/Observers/MemberObjectObserver.php`, `ActivityMemberObserver.php`, `MemberObserver.php`, `StorageSpaceRentalObserver.php`
- `app/Policies/InvoicePolicy.php`, `SepaBatchPolicy.php`, `PurchaseOrderPolicy.php`
- `app/Console/Commands/{GenerateInvoiceCommand,GenerateSepaBatchCommand,MatchBankTransactionsCommand}.php`
- `app/Providers/AppServiceProvider.php` (`SepaConfiguration`)
- `app/Filament/**` — many resources, actions, relation managers, tables, schemas and the `Labels/{InvoiceStatusLabels,SepaBatchStatusLabels,BillPeriodLabels,PurchaseOrderProblemLabels,PurchaseOrderStatusLabels}.php`
- `routes/mailbook.php` (`SepaBatchEmailData`, `SepaBatchId`, `InvoiceMailData`, `InvoiceMailLine`, `Mails\InvoiceMail`, `Mails\SepaBatchCreatedMail`, `SepaConfiguration`)
- `lang/nl/labels.php` (`BillPeriod`)
- `database/factories/{InvoiceFactory,SepaBatchFactory,PurchaseOrderFactory,BankStatementFactory,BankTransactionFactory,BillableItemFactory,ActivityFactory,MembershipFactory,StorageSpaceLocationFactory}.php` and `database/seeders/{MemberObjectTypeSeeder,StorageSpaceLocationSeeder,ActivitySeeder,MembershipSeeder}.php`
- Tests: `tests/Concerns/TestsBankingLifecycle.php`, `tests/Feature/Scenarios/PaymentMatchingScenarioTest.php`, `tests/Feature/Models/*`, `tests/Feature/Observers/*`, `tests/Feature/Authorization/*`, `tests/Feature/Filament/**`, `tests/Feature/Validation/**`, `tests/Unit/Domain/Mail/**`

## Navigation group rename

The model and the admin chrome should agree. Rename the enum case and its label key.

`app/Filament/Admin/Navigation/NavigationGroup.php`:

```php
enum NavigationGroup: string implements HasLabel
{
    case MemberAdministration = 'member_administration';
    case Billing = 'billing';          // was: Invoicing = 'invoicing'
    case Bookkeeping = 'bookkeeping';
    // ...
}
```

`lang/nl/labels.php` — replace the `invoicing` key:

```php
'navigation_groups' => [
    // ...
    'billing' => 'Facturatie',        // was: 'invoicing' => ...
],
```

Update the three resources that point at the old case (`app/Filament/Admin/Resources/{Invoices/InvoiceResource,PurchaseOrders/PurchaseOrderResource,SepaBatches/SepaBatchResource}.php`) to `NavigationGroup::Billing`. The `FilamentResourceNavigationGroupRule` only checks that a case is referenced, so no rule changes are needed.

## Orphan and duplicate test cleanup

The cleanup the user signed off on. These paths are the post-move locations (the wholesale `Invoices` → `Billing/Invoices` move has already run):

```bash
# Exact/subset duplicates of live tests under tests/Unit/Domain/Billing/BillableItems/ — delete.
git rm tests/Unit/Domain/Billing/Invoices/BillableItemIdTest.php
git rm tests/Unit/Domain/Billing/Invoices/BillableItemIdListTest.php
git rm tests/Unit/Domain/Billing/Invoices/BillableItemListTest.php

# The only test for the BillPeriod enum — relocate to the submodule that owns it.
git mv tests/Unit/Domain/Billing/Invoices/BillPeriodTest.php tests/Unit/Domain/Billing/BillableItems/BillPeriodTest.php

# Expectation classes move next to the tests that consume them.
git mv tests/Unit/Domain/Billing/Invoices/BillableItemRepositoryExpectation.php \
       tests/Unit/Domain/Billing/Subscriptions/BillableItemRepositoryExpectation.php
```

Two expectation classes back different `BillableItemInstanceRepository` call patterns and **both stay**, relocated with their consumers — do not merge them in this plan:

- `tests/Unit/Domain/Billing/Subscriptions/BillableItemInstanceRepositoryExpectation.php` (used by `ApplyStoppedMembershipImplTest`, `ApplyStorageSpaceRentalBillingImplTest`).
- `tests/Unit/Domain/Billing/Subscriptions/BillableItemRepositoryExpectation.php` (used by the other applicator tests; the name is misleading — leave as documented debt).

`BillableItemsViewRepositoryExpectation.php` is consumed by the invoice-generator tests, so it stays under `tests/Unit/Domain/Billing/Invoices/` (it lands there with the wholesale move) and merely needs its `namespace` line rewritten to the `Invoices` submodule.

## Billing and Bookkeeping documented model

### Context map

`.agents/docs/CONTEXT-MAP.md` — the contexts section becomes (the map must state code locations):

```markdown
- [Members](./members/CONTEXT.md) (`app/Domain/Members`, `app/Domain/Registration`) — member profiles, membership tiers, households, public registration.
- [Billing](./billing/CONTEXT.md) (`app/Domain/Billing`) — billable items, member subscriptions, invoice documents, the SEPA payment rail and purchase orders. Submodules: `BillableItems`, `Subscriptions`, `Invoices`, `Payment`, `PurchaseOrders`.
- [Bookkeeping](./bookkeeping/CONTEXT.md) (`app/Domain/Bookkeeping`) — cost centres and budgets, ledger records, and the bank register/statements/matching. Submodules: `CostCenters`, `BookkeepingRecords`, `Banking` (`BankAccounts`, `BankStatements`, `BankTransactions`).
- [Storage Space Rentals](./storage-space-rentals/CONTEXT.md) (`app/Domain/StorageSpaceRentals`) — ...
- [Activities](./activities/CONTEXT.md) (`app/Domain/Activities`) — ...
- [Mail](./mail/CONTEXT.md) (`app/Domain/Mail`) — ...
- Shared Kernel (`app/Domain/SharedKernel`) — `CompoundPrice` and `PriceFormatter`.
```

(The "Technical support modules" and "External partners" sections stay; only their references to the old context names are re-pointed.)

The Relationships section is rewritten so the seams are intra-context where they are now submodules and cross-context only where they are not:

```markdown
- **Members → Billing**: Members hold active subscriptions; billing applicators listen to member lifecycle events.
- **Billing → Members**: subscriptions reference `MemberId`; invoices use member SEPA mandate data.
- **Billing (Internal)**: `Invoices` reads subscriptions through `BillableItemsViewRepository`; `Payment` reads invoices and purchase orders for the export.
- **Billing → Bookkeeping**: billable items and invoice lines carry a `CostCenterId`.
- **Bookkeeping → Billing**: `BookkeepingRecords` and `Banking` matching reference `InvoiceIdList`, `SepaBatchId` and `PurchaseOrderIdList`. This Billing ⇄ Bookkeeping cycle is known and accepted.
- **Billing → Activities / Storage Space Rentals**: activity and rental locations reference a `BillableItem`.
```

Note the known cycle explicitly; do not try to break it.

### `billing/CONTEXT.md`

Rewrite `.agents/docs/billing/CONTEXT.md` so it has a submodule section per submodule, and absorb the terms currently in `invoicing/CONTEXT.md`:

```markdown
# Billing

One bounded context covering what members owe, the documents that request it, the SEPA rail that collects it, and the purchase orders that pay association expenses. Cost centres are owned by Bookkeeping; Billing references them by `CostCenterId`.

## Submodules

- **BillableItems** (`app/Domain/Billing/BillableItems`) — templates: **BillableItem**, **BillPeriod**.
- **Subscriptions** (`app/Domain/Billing/Subscriptions`) — per-member instances and the billing applicators.
- **Invoices** (`app/Domain/Billing/Invoices`) — invoice documents: **Invoice**, **InvoiceLine**, **InvoiceStatus**, invoice emailing.
- **Payment** (`app/Domain/Billing/Payment`) — the SEPA rail: **SepaBatch**, **SepaBatchStatus**, **SEPAExport**, **SEPA**, **MandateId**.
- **PurchaseOrders** (`app/Domain/Billing/PurchaseOrders`) — **PurchaseOrder**, **PurchaseOrderLine**, **PurchaseOrderStatus**, **Creditor**.

## Language

... (keep the existing BillableItem / BillableItemInstance / BillPeriod / Prorate / BillingApplicator terms,
    and move the Invoice / InvoiceLine / InvoiceStatus / SepaBatch / SepaBatchStatus / InvoiceNumber / SEPAExport / InvoiceMail terms here)
```

Delete `.agents/docs/invoicing/` and `.agents/docs/purchase-orders/` after folding their content in.

### `bookkeeping/CONTEXT.md`

Absorb `banking/CONTEXT.md` into `.agents/docs/bookkeeping/CONTEXT.md` as a `Banking` submodule section (keep every existing Banking term: BankAccount, BankAccountYearBalance, BankAccountBalanceOverview, BankStatement, StatementIntegrityStatus, StatementChainStatus, BankTransaction, ImportHash, BankTransactionStatus, ResolveStatus, TransactionMatching, MatchedAmount, Reversal, InternalTransfer). Delete `.agents/docs/banking/`.

### Glossary

`.agents/docs/GLOSSARY.md` — owner annotations move to the owning submodule:

- `Invoice`, `SepaBatch`, `SEPA` → `Billing (Payment/Invoices submodules)`.
- `BillableItem` → `Billing (BillableItems submodule)`; `BillableItemInstance` → `Billing (Subscriptions submodule)`.
- `BankTransaction` → `Bookkeeping (Banking submodule)`.
- `CostCenter` → `Bookkeeping (CostCenters submodule)`.
- `CompoundPrice`, `Member`, `ExtraMembershipItemCode`, `OutgoingEmail`, `Household` — unchanged.

`docs/architecture.md` — update the examples that name contexts and namespaces: the "Organised by bounded context" list, the `InvoiceRepository` example, `App\Infrastructure\Invoices\InvoiceRepositoryDb` → `App\Infrastructure\Billing\Invoices\InvoiceRepositoryDb`, and the matching `app/Domain/Invoices/InvoiceRepository` reference.

## ADR 0009

Append to `.agents/docs/ADR-MAP.md`:

```markdown
## 0009-finance-context-consolidation

The five finance contexts the code shipped — Billing, Invoicing, Purchase Orders, Banking and Bookkeeping — were consolidated into two. Billing became one bounded context with the submodules `BillableItems`, `Subscriptions`, `Invoices`, `Payment` and `PurchaseOrders` under `app/Domain/Billing`, absorbing the former Invoicing and Purchase Orders contexts and splitting the SEPA rail out of Invoices into `Payment`. Banking (`BankAccounts`, `BankStatements`, `BankTransactions`) became the `Banking` submodule of Bookkeeping. The split duplicated admin navigation groups and forced ports and DTOs between code that changes together, so the umbrella now matches the way the association talks about its finances and the number of top-level contexts drops from nine to six. This supersedes 0005: Billing is the umbrella again, but as distinct sub-namespaces rather than one flat namespace, so imports still show direction. ADR 0006's coupling rule now stops at the submodule edge — submodules of one context may import each other's concrete classes. The trade-off is a wider Billing context and an explicit Billing ⇄ Bookkeeping cycle (Billing writes `CostCenterId`; Bookkeeping matching reads invoice, batch and purchase-order IDs), which already existed in code and is accepted rather than resolved.
```

Also amend `0005-billing-context` with a one-line note that it is superseded by 0009.

## Verification

After the moves and rewrites:

```bash
./Taskfile artisan optimize:clear        # drop the cached autowire map
./Taskfile stan                          # domain purity + UnitTestRequired for every moved domain class
./Taskfile lint                          # mago lint + stan
./Taskfile artisan test --compact
```

Then confirm nothing was missed:

```bash
git grep -n -E 'App\\Domain\\(Invoices|PurchaseOrders|BankAccounts|BankStatements|BankTransactions)\\'
git grep -n -E 'App\\Infrastructure\\(Invoices|PurchaseOrders|BankAccounts|BankStatements|BankTransactions)\\'
git grep -n -E 'Tests\\(Unit\\Domain|Feature\\Infrastructure)\\(Invoices|PurchaseOrders|BankAccounts|BankStatements|BankTransactions)\\'
```

All three must return nothing. They are anchored to the segment immediately after `App\Domain\`, `App\Infrastructure\` and `Tests\Unit\Domain\` / `Tests\Feature\Infrastructure\`, so they deliberately do not match the legitimate `App\Domain\Billing\{Invoices,PurchaseOrders}` and `App\Domain\Bookkeeping\Banking\{BankAccounts,BankStatements,BankTransactions}` paths. `git grep -n 'Invoicing' -- .agents docs` should only match the superseded-by note added to ADR 0005.
