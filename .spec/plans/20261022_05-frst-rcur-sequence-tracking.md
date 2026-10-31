# FRST/RCUR sequence-type tracking — implementation plan

Date: 2026-10-22
Backlog step: 5 of 13 in [Prioritize the confirmed backlog and decide the plan split](../maps/20261021-missing-features/tickets/07-prioritize-confirmed-backlog-and-decide-plan-split.md)
Decisions source: [Judge SEPA & payment robustness candidates](../maps/20261021-missing-features/tickets/04-judge-sepa-payment-robustness-candidates.md), gap 2
Prerequisites: [Plan 02 — export readiness & submission tracking](20261022_02-export-readiness-submission-tracking.md), [Plan 04 — mandate lifecycle](20261022_04-mandate-lifecycle.md)
Issue: [blackshadev/jibe-desk#2 — Track FRST/RCUR sequence types through batch close, export and readiness](https://github.com/blackshadev/jibe-desk/issues/2)

## Overview

Announce direct debits with the right sequence type: `FRST` for the first collection under a mandate, `RCUR` for
every collection after it. There is no `FNAL` in this plan. A batch that contains both kinds still produces **one**
pain.008 file, but with **two `PmtInf` blocks** — one per sequence type — each carrying the batch's incassodatum
(`sepa_transfer_date`). A batch with only one kind emits only that block.

The mandate side is a prerequisite: plan 04 gives every mandate a `last_collected_at`, stamped at submission, and
`SepaExportInvoice` will already expose `paymentInformationId` and `lastCollectedAt`. This plan consumes those facts
and makes plan 02's vacuous `SequenceType` seam real: the export rows, the collection candidates and
`MandateReadiness::$expectedSequenceType` all get a true value.

The hard part is not the XML: it is the re-export case plan 04's hand-off flags. `last_collected_at` is stamped at
submission, so a naive `last_collected_at !== null → RCUR` flips an already-submitted first collection from FRST to
RCUR when the batch is downloaded again — a different file for the same collection, which is exactly the kind of
scheme violation this plan exists to prevent. This plan resolves the sequence **once, when the batch closes, and
freezes it on the invoice**; the exporter and readiness then read the frozen value, and a re-export reproduces the
presented file.

One nullable column on `invoices` is the only schema change. It is justified below: after submission
`last_collected_at` is a max-value field and the information needed to distinguish "this batch's own stamp" from "a
later batch's stamp" is gone, so no predicate over the current mandate row can be stable. The plan records the
decision while it is still knowable.

## Current situation

- `app/Infrastructure/Billing/Payment/SepaExportServiceImpl.php:64-73` creates exactly one direct-debit
  `PmtInf` for the whole batch with `'seqType' => PaymentInformation::S_RECURRING` (line 69). The invoice loop
  (lines 77-107) adds every positive invoice to that one block by name (`$paymentInfoId`), negatives to the
  credit-transfer facade, purchase orders afterwards (109-122). The direct-debit XML is only rendered when at least
  one positive invoice exists (`$hasDirectDebit`, lines 76/126), which is also what keeps the library's
  "at least one PaymentInformation" validation from firing on an empty file.
- `app/Domain/Billing/Payment/SepaExportInvoice.php:11-22` carries the mandate identity and signed date today;
  plan 04 adds `paymentInformationId` and `lastCollectedAt`. It has no sequence field, and the exporter has no
  access to the mandate row itself.
- `app/Infrastructure/Billing/Payment/SepaBatchRepositoryDb.php:107-150` — `getInvoicesForExport` joins
  `member.paymentInformation` and selects the mandate facts per invoice. Plan 04 rewrites it to a two-step read
  (invoice totals, then the member's active mandate for collections / latest mandate for refunds) with plan 02's
  `status = Pending` predicate. There is no sequence anywhere in that query.
- Plan 02 adds `getCollectionCandidates` (per-invoice totals, status and a **hardcoded**
  `SequenceType::Recurring` at `20261022_02-export-readiness-submission-tracking.md:485`), `getExportContext`
  (`status`, `sepaTransferDate`, `closedAt`) and the `ExportReadinessServiceImpl` comparison
  `expectedSequenceType !== candidate->sequenceType`.
- Plan 04 rewrites `MandateReadinessServiceImpl` and deliberately leaves `expectedSequenceType` at
  `SequenceType::Recurring` (`20261022_04-mandate-lifecycle.md:843,861-864`) until this plan flips both sides of the
  comparison together. It stamps `last_collected_at` at **submission** with the same clock value that
  `markSubmitted` writes to `sepa_batches.submitted_at` (plan 04, `SepaBatchServiceImpl::markSubmitted`).
- `app/Domain/Billing/Payment/MandateId.php:13-16` derives the reference as `C{member:06d}-{row:06d}`. A
  re-authorisation is a new `payment_information` row (plan 04), so it gets a new reference and a null
  `last_collected_at` — its first collection is FRST again without any extra rule.
- `app/Domain/Billing/Payment/SepaConfiguration.php:9-14` holds the four creditor values (plan 02 adds
  `noticePeriodDays`); nothing else is needed here.
- `app/Domain/Billing/Payment/SequenceType` does not exist yet, and neither does
  `app/Domain/Billing/Payment/Readiness/`. Both are plan 02's identifiers; this plan consumes and fills them.
- `app/Models/SepaBatch.php:28-35,57-68` has no `closed_at` / `exported_at` / `submitted_at` yet (plan 02 adds
  them); `app/Models/Invoice.php:72-79` has no sequence cast.
- `tests/Feature/Infrastructure/Billing/Payment/SepaExportServiceImplTest.php:460-497` builds debit invoices
  without a sequence, so every existing exporter test asserts the single-RCUR behaviour this plan replaces.
  `tests/Feature/Infrastructure/Billing/Payment/SepaBatchRepositoryDbTest.php:36` constructs the repository with
  no arguments, which changes when the resolver is injected.
- Two unit-test classes cover the same batch service (`SepaBatchServiceImplTest`, `SepaBatchServiceTest`); both
  need the new close/export expectations, as plan 02 and plan 04 already note.

### Vendor API facts (verified against `vendor/digitick/sepa-xml`)

The installed package is `digitick/sepa-xml` `^3.1`. Two `PmtInf` blocks in one pain.008 file are supported without
patching:

- `TransferFileFacadeFactory::createDirectDebit($uniqueMessageId, $initiatingPartyName)` returns a
  `CustomerDirectDebitFacade` (`TransferFile/Factory/TransferFileFacadeFactory.php:46-62`). The `pain.008.001.09`
  DOM builder emits `SeqTp` verbatim from the payment information
  (`DomBuilder/CustomerDirectDebitTransferDomBuilder.php:89`) and `ReqdColltnDt` per block from the payment's due
  date (line 91).
- `CustomerDirectDebitFacade::addPaymentInfo(string $paymentName, array $paymentInformation)` stores each block in
  `$this->payments[$paymentName]` and **throws `InvalidArgumentException` if the same name is used twice**
  (`TransferFile/Facade/CustomerDirectDebitFacade.php:52-77`). The `id` key becomes `PmtInfId` via
  `PaymentInformation` (`DomBuilder/CustomerDirectDebitTransferDomBuilder.php:54`); the library never enforces id
  uniqueness, but `PmtInfId` identifies the block in the file, so the two blocks must get distinct ids.
- `CustomerDirectDebitFacade::addTransfer(string $paymentName, array $transferInformation)` routes the transfer to
  the block registered under that name and throws when the name is unknown (lines 108-116). Grouping therefore
  needs no post-processing.
- `BaseCustomerTransferFileFacade::finalize()` pushes **every** registered `PaymentInformation` into the transfer
  file on the first `asXML()` (`TransferFile/Facade/BaseCustomerTransferFileFacade.php:133-145`);
  `CustomerDirectDebitTransferFile::validate()` requires at least one payment information and only requires
  `seqType` to be non-empty (`TransferFile/CustomerDirectDebitTransferFile.php:46-56`,
  `TransferFile/BaseTransferFile.php:83-88`).
- `PaymentInformation::setSequenceType()` stores the string without validation
  (`PaymentInformation.php:408-411`). The constants are `S_FIRST = 'FRST'`, `S_RECURRING = 'RCUR'`,
  `S_ONEOFF = 'OOFF'`, `S_FINAL = 'FNAL'` (lines 38-53). Passing `SequenceType->value` is therefore both valid and
  the single source of truth; do not map through a second switch.

Consequence: a sequence type with no invoices must **not** create a block (it would emit an empty `PmtInf` and a
zero `NbOfTxs`/`CtrlSum`), and each emitted block needs its own name **and** id.

## Planned changes

| Change | Main files |
|---|---|
| Freeze column for the resolved sequence per invoice | one migration, `Invoice` |
| One resolver for FRST/RCUR, shared by export and readiness | `MandateSequenceResolver` |
| Resolve and freeze the plan at batch close (and on first export of a legacy batch) | `SepaBatchRepository`, `SepaBatchRepositoryDb`, `SepaBatchServiceImpl` |
| Fill the export and candidate read models from the mandate and the frozen plan | `SepaBatchRepositoryDb`, `SepaExportInvoice` |
| Emit two direct-debit blocks, one per sequence type | `SepaExportServiceImpl` |
| Keep readiness consistent without false mismatches | `SepaBatchExportContext`, `ExportReadinessServiceImpl`, `MandateReadinessServiceImpl` |
| Re-plan a stale sequence on a Pending, unexported batch | `SepaBatchService`, `SepaBatchRepository`, `SepaBatchRepositoryDb`, `EditSepaBatch`, `SepaBatchPolicy` |
| Re-plan a reopened declined invoice | `InvoiceRepositoryDb` (plan 01's `reopen`) |
| Document the frozen-plan semantics | `.agents/docs/billing/CONTEXT.md` |

## The sequence rule

### FRST, RCUR, and no FNAL

- A mandate that has **never been collected** (`last_collected_at === null`) is `FRST`.
- Every collection after the first is `RCUR`.
- `FNAL` is not emitted. A member leaving the association still gets a normal recurring collection; the last
  debit is not announced as final.
- The sequence belongs to the **collection**, i.e. to the pair (mandate, batch). It is not a property of the member
  or of the mandate forever: the same mandate is FRST once and RCUR afterwards.

### Why the plan is frozen at batch close

`last_collected_at` is stamped at submission by plan 04. If the exporter re-derived the sequence on every export
with `last_collected_at !== null → RCUR`, then:

- the batch closes while the mandate was never collected → the exporter emits FRST;
- the batch is submitted → plan 04 stamps `last_collected_at`;
- finance re-downloads the same batch (allowed by plan 02) → the exporter now emits RCUR for the same collection.

The member's bank receives two different versions of one collection, and the first one may already have been
processed. The fix is to resolve the sequence **once, at close**, when the pre-collection state is still readable,
and to store it on each invoice. Everything after that reads the stored value.

The obvious alternative — comparing `last_collected_at` with the batch's `submitted_at` (both hold the same clock
value after plan 04's `markSubmitted`) — works only for the simple case above. It breaks on a *second* collection
that is submitted and then re-exported: `stampCollections` moves `last_collected_at` forward to the second batch's
submission time, so `last_collected_at == submitted_at` is true for **every** submitted batch, not only a first
collection. A rule keyed on that equality would label the re-export of a genuine second collection as FRST. The
information needed to distinguish the two (what `last_collected_at` was *before* this batch stamped it) has been
overwritten, and no predicate over the current mandate row can recover it.

That is why a stored plan is required rather than a cleverer condition. The stored plan is also what makes the
readiness check meaningful: readiness compares the plan written at close with the mandate's state at export time,
so a plan that went stale between close and export is visible instead of silently re-derived. A stale plan is
correctable through the re-plan action below until the batch is exported; after the first export the frozen plan is
the presented history and cannot change.

### Why one nullable column is unavoidable

Rejected alternatives:

- **A column on `sepa_batches`.** A batch can contain both FRST and RCUR collections, so one batch-level value is
  wrong by construction.
- **A separate `sepa_batch_invoice_sequences` table.** Correct but strictly more machinery (table, model, join,
  cascade) for one nullable fact per invoice. The invoice already belongs to exactly one batch (`invoices.sepa_batch_id`),
  and plan 01's reopen detaches it; the same lifecycle applies to the plan.
- **A JSON map on the batch.** Unqueryable, invisible to the per-invoice read models, and harder to keep consistent
  than a column.
- **Deriving it on every read.** Shown above to be unstable across re-exports.

The column is `invoices.sepa_sequence_type`, nullable, four characters, no default. `NULL` means "no plan recorded
yet" and falls back to the mandate (`last_collected_at`), which keeps every legacy batch and every freshly
generated invoice readable before its first close. It is written once, at close, and never overwritten while the
invoice stays in that batch.

### The resolver

One pure domain class owns the only conditional:

`app/Domain/Billing/Payment/MandateSequenceResolver.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Billing\Payment;

use DateTimeInterface;

/**
 * Resolves the FRST/RCUR announcement for a collection.
 *
 * A frozen sequence is the plan recorded when the batch closed; it wins so a
 * re-export reproduces the presented file. Without a plan the mandate decides:
 * a mandate that was never collected is on its first collection.
 */
final readonly class MandateSequenceResolver
{
    public function resolve(
        ?DateTimeInterface $lastCollectedAt,
        ?SequenceType $frozenSequence = null,
    ): SequenceType {
        if ($frozenSequence !== null) {
            return $frozenSequence;
        }

        return $lastCollectedAt === null ? SequenceType::First : SequenceType::Recurring;
    }
}
```

Used by exactly three call sites, all passing it the facts they already have:

- `SepaBatchRepositoryDb::getCollectionCandidates` — `last_collected_at` from the active-mandate join, frozen
  value from `invoices.sepa_sequence_type`.
- `SepaBatchRepositoryDb::getInvoicesForExport` — the same two facts from the plan-04 read.
- `MandateReadinessServiceImpl::forMembers` — the active mandate's `last_collected_at`, no frozen value (a member
  is not a batch).

No SQL `CASE WHEN`, no second `=== null ? First : Recurring` anywhere. The exporter never interprets mandate facts
at all; it consumes `SepaExportInvoice::$sequenceType`.

### The four lives of a mandate

| Situation | `last_collected_at` at close | Frozen plan | Export |
|---|---|---|---|
| Never collected | `null` | `FRST` | one `PmtInf` with `SeqTp=FRST` |
| Collected in an earlier batch | a previous submission time | `RCUR` | one `PmtInf` with `SeqTp=RCUR` |
| Re-authorised (plan 04) | `null` on the **new** row; the old row is revoked | `FRST` | new mandate reference, `SeqTp=FRST` |
| First collection declined (plan 01) | still stamped at submission, never cleared | `RCUR` for the retry batch | re-presented as recurring |

Details that matter:

- **Re-authorisation.** The old row keeps its `last_collected_at` and its derived reference; it is revoked and the
  active-mandate join no longer sees it. The new row starts null, so the first collection under it is FRST without
  any special case. The frozen plan is per invoice, and a reopened/re-batched invoice carries no plan (see below).
- **Declined first collection.** Plan 04 stamps at submission because "presented counts as use". Plan 01's decline
  path must not clear it (plan 04's hand-off already states this). The retry therefore closes with `RCUR`, which is
  the confirmed decision.
- **Re-export.** The frozen plan wins over the now-stamped mandate, so FRST stays FRST and RCUR stays RCUR.
- **Re-authorisation between close and export.** The invoice is collected under the new, never-used mandate. If the
  frozen plan was FRST, it still is and the file is correct; if the frozen plan was RCUR (the old mandate had been
  used), the plan is stale and readiness reports a mismatch (see below) instead of silently shipping a wrong
  sequence.

## Freeze column, model and migration

`database/migrations/2026_10_22_120000_add_sepa_sequence_type_to_invoices_table.php`:

```php
return new class() extends Migration {
    public function up(): void
    {
        Schema::table('invoices', static function (Blueprint $table): void {
            $table->string('sepa_sequence_type', 4)->nullable()->after('sepa_batch_id');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', static function (Blueprint $table): void {
            $table->dropColumn('sepa_sequence_type');
        });
    }
};
```

No data migration. `NULL` is a valid state (derive from the mandate), plan 04's backfill already reconstructs
`last_collected_at` for historical batches, and a DDL-only migration follows the project's "one concern per
migration" rule. Legacy batches get their plan written by the export-time freeze below, so they become stable after
their first export under this plan. The migration is numbered `120000`, after plan 02's `100000`–`100002` and plan
04's `110000`–`110002`.

`app/Models/Invoice.php` — add the import, the property annotation and the cast:

```php
use App\Domain\Billing\Payment\SequenceType;

/**
 * @property InvoiceStatus $status
 * @property DateTimeInterface $date
 * @property DateTimeInterface|null $sent_at
 * @property SequenceType|null $sepa_sequence_type
 */
```

```php
return [
    'date' => 'datetime',
    'sent_at' => 'datetime',
    'status' => InvoiceStatus::class,
    'sepa_sequence_type' => SequenceType::class,
];
```

The cast is what makes `$invoice->sepa_sequence_type` a `SequenceType` in tests and Filament; without it the column
reads back as a raw string and the resolver's typed parameter would need a manual conversion at every call site.

## Freezing the plan

### Repository contract

`app/Domain/Billing/Payment/SepaBatchRepository.php` gains:

```php
/**
 * Record the FRST/RCUR plan of every collectable invoice in the batch.
 * Existing plans are never overwritten.
 */
public function freezeSequenceTypes(SepaBatchId $batchId): void;
```

### Implementation

`app/Infrastructure/Billing/Payment/SepaBatchRepositoryDb.php` gains the resolver through constructor injection
(the class currently has no constructor; feature tests must be updated accordingly). It also needs
`use App\Domain\Billing\Payment\MandateSequenceResolver;`, `use App\Domain\Billing\Payment\SequenceType;` and
`use Illuminate\Database\Query\JoinClause;`:

```php
public function __construct(
    private MandateSequenceResolver $sequenceResolver,
) {}
```

```php
#[Override]
public function freezeSequenceTypes(SepaBatchId $batchId): void
{
    /** @var array<string, list<int>> $invoiceIdsBySequenceType */
    $invoiceIdsBySequenceType = [];

    foreach ($this->getCollectionCandidates($batchId) as $candidate) {
        if (!$candidate->isDebit()) {
            continue;
        }

        $invoiceIdsBySequenceType[$candidate->sequenceType->value][] = $candidate->invoiceId->value;
    }

    foreach ($invoiceIdsBySequenceType as $sequenceType => $invoiceIds) {
        Invoice::query()
            ->whereIn('id', $invoiceIds)
            ->whereNull('sepa_sequence_type')
            ->update(['sepa_sequence_type' => $sequenceType]);
    }
}
```

Notes:

- It reuses `getCollectionCandidates`, so the freeze and the readiness report can never disagree about which
  invoices are in scope or what their sequence is.
- `whereNull` makes it idempotent: re-closing cannot happen (only Open batches close), but exporting a Pending batch
  calls it again as a safety net, and neither may change a recorded plan.
- Refunds are skipped: the credit-transfer file has no `SeqTp` and a payout must not consume a sequence.
- Invoices of members without an active mandate are frozen as FRST (the resolver sees a null). That is harmless:
  the invoice is not exported while the member is a manual payer, and if a mandate is later signed it is a new,
  never-collected mandate whose first collection *is* FRST. If the invoice is later detached from the batch (plan
  01's reopen), the value is cleared.

### Call sites

`app/Domain/Billing/Payment/SepaBatchServiceImpl.php::closeBatch` — freeze after the invoices are released and
before the batch leaves the Open state:

```php
#[Override]
public function closeBatch(SepaBatchId $batchId): void
{
    $this->batchRepository->markInvoicesAsPending($batchId);
    $this->batchRepository->freezeSequenceTypes($batchId);
    $this->bookkeepingRepository->createForBatch($batchId);
    $this->batchRepository->closeBatch($batchId);

    $this->eventDispatcher->dispatch(new SepaBatchClosed(batchId: $batchId));
}
```

Plan 02's `export`, after the readiness gate and before generating the XML:

```php
$report = $this->readinessService->assess($batchId);

if (!$report->isSatisfiedBy($confirmation)) {
    throw new ExportNotReadyException($report);
}

$this->batchRepository->freezeSequenceTypes($batchId);

$export = $this->exportService->export($batchId);

$this->batchRepository->recordExport($batchId, $confirmation, $exportedByUserId, $this->clock->now());
```

Ordering is deliberate:

- At close, the mandate still holds the *pre-collection* state, which is the only moment the plan is knowable.
- At export, the gate judges the plan written at close. Freezing before the gate would make the comparison
  tautological and the staleness check dead.
- The export-time freeze is a safety net for batches that were closed before this migration (no plan yet): it
  records the resolved value before the first XML is generated, so even their first re-export is stable.

### Clearing the plan when an invoice leaves its batch

Plan 01's `InvoiceRepositoryDb::reopen` detaches a declined invoice from its batch. The frozen plan must go with the
batch, otherwise the retry inherits it:

```php
Invoice::query()
    ->whereIn('id', $reopenedIds->values())
    ->update([
        'status' => InvoiceStatus::Open,
        'sepa_batch_id' => null,
        'sent_at' => null,
        'sepa_sequence_type' => null,
    ]);
```

The same rule applies to any future detach path: **the plan belongs to the batch attachment**. There is no detach
action today, and `addOpenInvoicesFromBatchMonth` / plan 01's `attachInvoices` only attach invoices with
`sepa_batch_id IS NULL` and status `Open`, so a cleared value is the only way an invoice re-enters a batch.

## Exporter restructure

`app/Infrastructure/Billing/Payment/SepaExportServiceImpl.php` — the credit-transfer facade, its block and the
purchase-order loop stay exactly as they are. The direct-debit side is grouped by sequence before any block is
created, then one block is emitted per non-empty group. Full method:

```php
/**
 * @throws InvalidArgumentException
 * @throws DOMException
 */
#[Override]
public function export(SepaBatchId $batchId): SepaExport
{
    $dueDate = $this->batchRepository->getSepaTransferDate($batchId);

    /** @var list<SepaExportInvoice> $invoices */
    $invoices = $this->batchRepository->getInvoicesForExport($batchId);

    /** @var list<SepaExportPurchaseOrder> $purchaseOrders */
    $purchaseOrders = $this->batchRepository->getPurchaseOrdersForExport($batchId);

    $creditPaymentInfoId = 'payment-batch-' . $batchId->value;
    $uniqueMessageId = 'BATCH-' . $batchId->value;

    $credit = TransferFileFacadeFactory::createCustomerCredit(
        $uniqueMessageId,
        $this->configuration->creditorName,
    );

    $credit->addPaymentInfo($creditPaymentInfoId, [
        'id' => $creditPaymentInfoId,
        'debtorName' => $this->configuration->creditorName,
        'debtorAccountIBAN' => $this->configuration->creditorIban,
        'debtorAgentBIC' => $this->configuration->creditorBic,
        'batchBooking' => true,
        'dueDate' => $dueDate,
    ]);

    $directDebit = TransferFileFacadeFactory::createDirectDebit(
        $uniqueMessageId,
        $this->configuration->creditorName,
    );

    /** @var array<string, list<SepaExportInvoice>> $debitsBySequenceType */
    $debitsBySequenceType = [];
    $hasCreditTransfer = false;

    foreach ($invoices as $invoice) {
        if ($invoice->amountInCents() === 0) {
            continue;
        }

        if ($invoice->amountInCents() < 0) {
            $hasCreditTransfer = true;
            $credit->addTransfer($creditPaymentInfoId, [
                'amount' => abs($invoice->amountInCents()),
                'creditorIban' => $invoice->iban,
                'creditorName' => $invoice->recipientName,
                'creditorBic' => $invoice->bic,
                'endToEndId' => $invoice->invoiceNumber,
                'remittanceInformation' => $invoice->invoiceNumber,
            ]);
            continue;
        }

        $debitsBySequenceType[$invoice->sequenceType->value][] = $invoice;
    }

    $hasDirectDebit = false;

    foreach (SequenceType::cases() as $sequenceType) {
        $debits = $debitsBySequenceType[$sequenceType->value] ?? [];

        if ($debits === []) {
            continue;
        }

        $directDebitPaymentInfoId = sprintf('BATCH-%d-%s', $batchId->value, $sequenceType->value);

        $directDebit->addPaymentInfo($directDebitPaymentInfoId, [
            'id' => $directDebitPaymentInfoId,
            'creditorName' => $this->configuration->creditorName,
            'creditorAccountIBAN' => $this->configuration->creditorIban,
            'creditorAgentBIC' => $this->configuration->creditorBic,
            'seqType' => $sequenceType->value,
            'creditorId' => $this->configuration->creditorId,
            'batchBooking' => true,
            'dueDate' => $dueDate,
        ]);

        $hasDirectDebit = true;

        foreach ($debits as $invoice) {
            $directDebit->addTransfer($directDebitPaymentInfoId, [
                'amount' => $invoice->amountInCents(),
                'debtorIban' => $invoice->iban,
                'debtorName' => $invoice->recipientName,
                'debtorBic' => $invoice->bic,
                'debtorMandate' => $invoice->mandateId->value,
                'debtorMandateSignDate' => $invoice->mandateDate,
                'endToEndId' => $invoice->invoiceNumber,
                'remittanceInformation' => $invoice->invoiceNumber,
            ]);
        }
    }

    foreach ($purchaseOrders as $purchaseOrder) {
        if ($purchaseOrder->amountInCents() === 0) {
            continue;
        }

        $hasCreditTransfer = true;
        $credit->addTransfer($creditPaymentInfoId, [
            'amount' => $purchaseOrder->amountInCents(),
            'creditorIban' => $purchaseOrder->creditorIban,
            'creditorName' => $purchaseOrder->creditorName,
            'endToEndId' => $purchaseOrder->reference(),
            'remittanceInformation' => $purchaseOrder->reference(),
        ]);
    }

    return new SepaExport(
        creditTransfers: $hasCreditTransfer ? $credit->asXML() : '',
        directDebit: $hasDirectDebit ? $directDebit->asXML() : '',
    );
}
```

Add `use App\Domain\Billing\Payment\SequenceType;` and keep the `PaymentInformation` import only if something else
uses it — after this change it does not, so remove it (mago lint flags unused imports).

Properties of the result, each covered by a test:

- **One block per non-empty sequence.** `SequenceType::cases()` yields `First` then `Recurring`, so the block order is
  deterministic (FRST first) and does not depend on invoice order.
- **No empty blocks.** A batch with only FRST invoices never creates an RCUR block, and vice versa. A batch with no
  positive invoices never calls `asXML()` on the direct-debit facade, so the library's "No PaymentInformation
  available" validation cannot fire.
- **Same incassodatum on every block.** Both `addPaymentInfo` calls pass the batch `$dueDate`, which the DOM builder
  writes as `<ReqdColltnDt>` per block.
- **Distinct `PmtInfId` and payment names.** `BATCH-{batch}-FRST` / `BATCH-{batch}-RCUR`; using the id as the name
  keeps the two aligned, and the names cannot collide (the library throws on a duplicate name).
- **No duplication.** Each invoice is appended to exactly one sequence bucket; its `EndToEndId` appears in exactly
  one block.
- **Untouched credit side.** Refunds and purchase orders still go into the single credit-transfer block under
  `$creditPaymentInfoId`, with the batch due date and the existing fields.

## Repository seam: reading the sequence

### `getCollectionCandidates`

Plan 02's query gains a left join to the **active** mandate (plan 04's partial unique index guarantees at most one
row) and reads the frozen plan from the invoice. Everything else is unchanged:

```php
/** @return list<SepaCollectionCandidate> */
#[Override]
public function getCollectionCandidates(SepaBatchId $batchId): array
{
    $rows = DB::table('invoices')
        ->leftJoin('invoice_lines', 'invoice_lines.invoice_id', '=', 'invoices.id')
        ->leftJoin('payment_information', static function (JoinClause $join): void {
            $join->on('payment_information.member_id', '=', 'invoices.member_id')
                ->whereNull('payment_information.revoked_at');
        })
        ->where('invoices.sepa_batch_id', $batchId->value)
        ->whereIn('invoices.status', [InvoiceStatus::Open, InvoiceStatus::Pending])
        ->groupBy(
            'invoices.id',
            'invoices.invoice_number',
            'invoices.recipient_name',
            'invoices.member_id',
            'invoices.status',
            'invoices.sepa_sequence_type',
            'payment_information.last_collected_at',
        )
        ->select(
            'invoices.id as invoice_id',
            'invoices.invoice_number',
            'invoices.recipient_name',
            'invoices.member_id',
            'invoices.status',
            'invoices.sepa_sequence_type',
            'payment_information.last_collected_at',
            DB::raw('COALESCE(SUM(invoice_lines.price * invoice_lines.quantity), 0) as total_price'),
            DB::raw('COALESCE(SUM(invoice_lines.vat * invoice_lines.quantity), 0) as total_vat'),
        )
        ->orderBy('invoices.id')
        ->get();

    return $rows->map(fn (object $row): SepaCollectionCandidate => new SepaCollectionCandidate(
        invoiceId: InvoiceId::create((int) $row->invoice_id),
        invoiceNumber: $row->invoice_number,
        recipientName: $row->recipient_name,
        memberId: $row->member_id === null ? null : MemberId::create((int) $row->member_id),
        total: new CompoundPrice((float) $row->total_price, (float) $row->total_vat),
        status: InvoiceStatus::from($row->status),
        sequenceType: $this->sequenceResolver->resolve(
            lastCollectedAt: $row->last_collected_at === null
                ? null
                : CarbonImmutable::parse((string) $row->last_collected_at),
            frozenSequence: $row->sepa_sequence_type === null
                ? null
                : SequenceType::from((string) $row->sepa_sequence_type),
        ),
    ))->all();
}
```

Why this and not "let readiness resolve it": `SepaCollectionCandidate` is plan 02's seam, and its `sequenceType`
is documented as the value the export will use. Keeping the resolution in the read model means readiness, the
freeze and the exporter's input all see one value. The read model gains no new field; only its existing field is
filled from real data.

Notes:

- The join is `whereNull('payment_information.revoked_at')`, matching plan 04's active-mandate relation. A revoked
  row is never joined, so the historical mandate of a re-authorised member cannot supply a stale sequence.
- `last_collected_at` and `sepa_sequence_type` are added to `groupBy` because every selected non-aggregate must be
  grouped on PostgreSQL; the codebase already selects `invoices.status` there for the same reason.
- For a member with no mandate the left join yields nulls; `resolve(null)` returns `First`. Readiness reports the
  member as a missing/revoked mandate before it ever looks at the sequence, so this cannot create a false mismatch.
- Use `fn` (not `static fn`) because the closure uses `$this->sequenceResolver`.

### `getInvoicesForExport`

Plan 04's two-step read already loads the mandate model, so only the invoice projection and the DTO construction
change:

```php
$invoices = Invoice::query()
    ->where('invoices.sepa_batch_id', $batchId->value)
    ->where('invoices.status', InvoiceStatus::Pending)
    ->joinRelationship('lines')
    ->select(
        'invoices.id as invoice_id',
        'invoices.invoice_number',
        'invoices.member_id',
        'invoices.recipient_name',
        'invoices.sepa_sequence_type',
        DB::raw('SUM(invoice_lines.price * invoice_lines.quantity) as total_price'),
        DB::raw('SUM(invoice_lines.vat * invoice_lines.quantity) as total_vat'),
    )
    ->groupBy(
        'invoices.id',
        'invoices.invoice_number',
        'invoices.member_id',
        'invoices.recipient_name',
        'invoices.sepa_sequence_type',
    )
    ->get();
```

and inside the existing row loop, after the mandate is selected:

```php
$rows[] = new SepaExportInvoice(
    invoiceId: InvoiceId::create((int) $invoice->invoice_id),
    invoiceNumber: $invoice->invoice_number,
    recipientName: $invoice->recipient_name,
    total: new CompoundPrice($totalPrice, (float) $invoice->total_vat),
    iban: $mandate->banking_account_number,
    bic: $mandate->banking_bic,
    mandateId: new MandateId(
        MemberId::create((int) $invoice->member_id),
        PaymentInformationId::create($mandate->id),
    ),
    paymentInformationId: PaymentInformationId::create($mandate->id),
    mandateDate: CarbonImmutable::parse($mandate->mandate_accepted_date),
    lastCollectedAt: $mandate->last_collected_at,
    sequenceType: $this->sequenceResolver->resolve(
        lastCollectedAt: $mandate->last_collected_at,
        frozenSequence: $invoice->sepa_sequence_type,
    ),
);
```

`$invoice` is an Eloquent model, so the `sepa_sequence_type` cast returns `SequenceType|null` directly; the raw
`DB::table` query in `getCollectionCandidates` needs the explicit `SequenceType::from()` shown above.

The frozen value is passed to the resolver as the plan, so a submitted batch keeps the sequence it was closed with
instead of re-deriving it from the now-stamped mandate.

### `SepaExportInvoice`

Plan 04's DTO gains one resolved field at the end:

```php
final readonly class SepaExportInvoice
{
    public function __construct(
        public InvoiceId $invoiceId,
        public string $invoiceNumber,
        public string $recipientName,
        public CompoundPrice $total,
        public string $iban,
        public string $bic,
        public MandateId $mandateId,
        public PaymentInformationId $paymentInformationId,
        public DateTimeInterface $mandateDate,
        public ?DateTimeInterface $lastCollectedAt,
        public SequenceType $sequenceType,
    ) {}
```

`paymentInformationId` and `lastCollectedAt` stay exactly as plan 04 defined them, and this plan does not add a
second copy of the mandate facts. The new `sequenceType` is the *resolved plan*: the repository computes it with
`MandateSequenceResolver` from those facts plus the invoice's frozen value. The exporter reads only
`$invoice->sequenceType`, so it cannot drift from what readiness saw.

## Readiness consistency

Two different questions must not be conflated:

- `SepaCollectionCandidate::$sequenceType` = **the plan for this collection** (frozen at close, or resolved now for
  an invoice without a plan).
- `MandateReadiness::$expectedSequenceType` = **what this mandate's next collection should be**, from
  `last_collected_at` alone.

Plan 02 compares them and raises `SequenceTypeMismatch` (category `BrokenData`, not overridable).

### `expectedSequenceType` is now real

`app/Infrastructure/Billing/Payment/MandateReadinessServiceImpl.php` (plan 04's rewrite) gains the resolver and
loses the literal:

```php
public function __construct(
    private MandateExpiry $mandateExpiry,
    private MandateSequenceResolver $sequenceResolver,
) {}
```

It needs `use App\Domain\Billing\Payment\MandateSequenceResolver;`; `SequenceType` is already imported for the
literal this change removes.

```php
$result[(int) $memberId] = new MandateReadiness(
    status: $status,
    iban: $mandate->banking_account_number,
    bic: $mandate->banking_bic,
    mandateId: new MandateId(
        MemberId::create((int) $memberId),
        PaymentInformationId::create($mandate->id),
    ),
    signedOn: $mandate->mandate_accepted_date,
    expectedSequenceType: $this->sequenceResolver->resolve($mandate->last_collected_at),
);
```

A never-collected mandate now reads `First`; a collected one reads `Recurring`. For a revoked or missing mandate the
value is irrelevant because plan 02 reports the mandate status first, but it is still computed from the latest row
so the object is always complete.

### Why a legitimate FRST passes

The first export of a first collection: the invoice was frozen to `FRST` at close and the mandate still has a null
`last_collected_at`, so `First === First`. The same holds for the close-time warning in plan 02 (the batch is still
Open, the candidate is not frozen yet, and both sides resolve from the same null). No special case is needed; the
two sides agree because they are derived from the same mandate state.

### Why the comparison is skipped for a presented batch

`SepaBatchExportContext` (plan 02) gains the submission moment:

```php
final readonly class SepaBatchExportContext
{
    public function __construct(
        public SepaBatchStatus $status,
        public DateTimeInterface $sepaTransferDate,
        public ?DateTimeInterface $closedAt,
        public ?DateTimeInterface $submittedAt = null,
    ) {}
}
```

and `SepaBatchRepositoryDb::getExportContext` passes `submittedAt: $batch->submitted_at`.

`ExportReadinessServiceImpl` changes exactly one condition:

```php
if ($context->submittedAt === null
    && $mandate->expectedSequenceType !== $candidate->sequenceType
) {
    $issues[] = ExportReadinessIssue::forInvoice(
        ExportReadinessReason::SequenceTypeMismatch,
        $candidate,
        sprintf(
            'Gepland: %s, mandaat verwacht: %s.',
            $candidate->sequenceType->value,
            $mandate->expectedSequenceType->value,
        ),
    );
}
```

This is the trap plan 04's hand-off warns about. After submission the mandate is stamped, so the expected value
becomes `Recurring` while a legitimate first-collection plan stays `FRST`. `last_collected_at` is a max-value
field: it cannot tell this batch's own stamp from a later batch's stamp, so a comparison after presentation either
false-positives on every first collection or, with a smarter-looking predicate, mislabels genuine second
collections. Once the file has been handed to the bank, the recorded plan **is** the history; re-exports must
reproduce it byte-for-byte in the sequence field. The check therefore applies to the window where a correction is
still possible: closed but not yet submitted.

### How a real mismatch is still detectable

With the check applied to unsubmitted batches:

- plan `FRST`, mandate `last_collected_at` non-null → another batch presented a collection under this mandate
  before this one; the plan is stale and the batch would announce a second collection as a first. **Mismatch.**
  (Typical cause: two overlapping rounds; the September batch was never exported while the October batch was
  submitted first.)
- plan `RCUR`, mandate `last_collected_at` null → the active row changed to a never-used mandate after close
  (re-authorisation), so this collection is the new mandate's first. **Mismatch.**
- plan `FRST`, mandate null → consistent, passes.
- plan `RCUR`, mandate non-null → consistent, passes.

The report detail names both values, so finance can see which side is stale without opening the XML.

## Re-planning a stale sequence

`SequenceTypeMismatch` is `BrokenData`, so it blocks export. A plan can go stale between close and export — for
example a re-authorisation, or an overlapping batch that was submitted first. A Pending batch has no other correction
path, so this plan adds a finance-only action that clears and re-freezes the plan while the batch is still correctable.
The strict gate stays; the action makes the correction deliberate instead of silently re-deriving on every read.

`app/Domain/Billing/Payment/SepaBatchRepository.php` gains:

```php
/**
 * Clear and re-freeze the sequence plan of a Pending, unexported batch.
 */
public function replanSequenceTypes(SepaBatchId $batchId): void;
```

Implementation in `SepaBatchRepositoryDb`:

```php
#[Override]
public function replanSequenceTypes(SepaBatchId $batchId): void
{
    DB::transaction(function () use ($batchId): void {
        $batch = SepaBatch::query()
            ->whereKey($batchId->value)
            ->lockForUpdate()
            ->firstOrFail();

        if (
            $batch->status !== SepaBatchStatus::Pending
            || $batch->exported_at !== null
            || $batch->submitted_at !== null
        ) {
            throw new DomainException('Only a pending, unexported and unsubmitted batch can be re-planned.');
        }

        Invoice::query()
            ->where('sepa_batch_id', $batchId->value)
            ->update(['sepa_sequence_type' => null]);

        $this->freezeSequenceTypes($batchId);
    });
}
```

Notes:

- The guard is the same one the Filament action uses: Pending, never exported and never submitted. Once a file has
  been presented, the frozen plan is history and must not change.
- Clearing first is required: `freezeSequenceTypes` never overwrites an existing plan, so a re-plan would otherwise be
  a no-op.
- Re-freezing resolves from the **current** active mandates, which is exactly what makes a re-authorisation correction
  work: the new row is never collected, so the invoice resolves to FRST and the export uses the new reference.
- The transaction and row lock prevent two finance users from re-planning the same batch concurrently.
- Only invoices attached to this batch are touched. Other batches and detached invoices keep their values.
- No audit row: the map has no admin audit trail, and the action is a deliberate, permission-gated correction before
  presentation. The readiness report immediately shows the new plan.

Expose it through `SepaBatchService` and `SepaBatchServiceImpl`:

```php
public function replanSequenceTypes(SepaBatchId $batchId): void;
```

```php
#[Override]
public function replanSequenceTypes(SepaBatchId $batchId): void
{
    $this->batchRepository->replanSequenceTypes($batchId);
}
```

Add the policy method:

```php
public function replanSequence(User $user, Model $batch): bool
{
    Assert::isInstanceOf($batch, SepaBatch::class);

    return $user->can('update_sepa_batches')
        && $batch->status === SepaBatchStatus::Pending
        && $batch->exported_at === null
        && $batch->submitted_at === null;
}
```

Add the action to `EditSepaBatch`, beside the export action:

```php
Action::make('replanSequence')
    ->label(__('labels.replan_sequence'))
    ->icon('heroicon-m-arrow-path')
    ->color('warning')
    ->requiresConfirmation()
    ->modalHeading(__('labels.replan_sequence'))
    ->modalDescription(__('labels.replan_sequence_warning'))
    ->visible(static fn (SepaBatch $record): bool =>
        auth()->user()?->can('replanSequence', $record) ?? false)
    ->action(static function (SepaBatch $record, SepaBatchService $service): void {
        $service->replanSequenceTypes(SepaBatchId::create($record->id));
    })
    ->successNotificationTitle(__('notifications.sequence_replanned'))
    ->after(static fn (EditRecord $livewire) => $livewire->dispatch('refreshInvoicesTable')),
```

Add to `lang/nl/labels.php`:

```php
'replan_sequence' => 'Incassovolgorde herplannen',
'replan_sequence_warning' => 'De eerste/volgende-incasso wordt opnieuw bepaald uit de huidige machtigingen. Doe dit alleen als de machtiging sinds het sluiten is gewijzigd; de export wordt hierna opnieuw gecontroleerd.',
```

Add to `lang/nl/notifications.php`:

```php
'sequence_replanned' => 'Incassovolgorde opnieuw bepaald',
```

## Glossary

Update the **SequenceType** entry in `.agents/docs/billing/CONTEXT.md` so the frozen plan is part of the
vocabulary:

```markdown
**SequenceType**:
How a direct debit is announced to the bank: FRST for the first collection under a Mandate, RCUR for every later
one. The mandate's last collection decides it when the SepaBatch closes; the resolved value is frozen on the
Invoice so a re-export repeats the presented file. A re-authorised mandate starts at FRST again.
_Avoid_: sequence code, first/recurring flag
```

## Tests

PHPUnit classes only. Create new files with `./Taskfile artisan make:test --phpunit <name>` (add `--unit` for domain
tests) and the migration with `./Taskfile artisan make:migration`. Use the existing factories and states from plan
04 (`PaymentInformationFactory::collectedAt()`, `::signedAt()`, `::revoked()`) and static PHPUnit assertions.
Every new non-abstract class under `app/Domain/**` needs a matching `tests/Unit/**` file
(`extrastan.UnitTestRequired`), which the resolver test provides.

### Unit — resolver

`tests/Unit/Domain/Billing/Payment/MandateSequenceResolverTest.php`:

- `test_resolve_returns_first_for_a_mandate_that_was_never_collected`
- `test_resolve_returns_recurring_for_a_mandate_with_a_last_collection`
- `test_resolve_prefers_a_frozen_first_over_a_stamped_mandate` (the re-export proof at the smallest level)
- `test_resolve_prefers_a_frozen_recurring_over_a_never_collected_mandate`
- `test_resolve_does_not_look_at_the_value_of_the_last_collection` (any non-null date → recurring)

### Unit — export invoice

`tests/Unit/Domain/Billing/Payment/SepaExportInvoiceTest.php`:

- extend `test_it_stores_all_properties` with `sequenceType`
- `test_it_keeps_the_sequence_type`

### Unit — batch service

`tests/Unit/Domain/Billing/Payment/SepaBatchServiceImplTest.php` and
`tests/Unit/Domain/Billing/Payment/SepaBatchServiceTest.php` (both construct the same service; keep them in sync):

- `test_close_batch_freezes_the_sequence_plan_before_closing` — ordered expectations:
  `markInvoicesAsPending`, `freezeSequenceTypes`, `createForBatch`, `closeBatch`, `SepaBatchClosed`.
- `test_replan_sequence_types_delegates_to_the_repository`.
- plan 02's `test_export_*` cases gain `freezeSequenceTypes` between the readiness assertion and the exporter call.

Add `expectsFreezeSequenceTypes(SepaBatchId $batchId)` to
`tests/Unit/Domain/Billing/Payment/SepaBatchRepositoryExpectation.php`.

### Unit — readiness context

`tests/Unit/Domain/Billing/Payment/Readiness/SepaBatchExportContextTest.php` (plan 02):

- `test_it_keeps_the_submission_moment`
- `test_it_defaults_the_submission_moment_to_null`

### Feature — repository

`tests/Feature/Infrastructure/Billing/Payment/SepaBatchRepositoryDbTest.php` (setUp becomes
`new SepaBatchRepositoryDb(new MandateSequenceResolver())`):

- `test_get_invoices_for_export_returns_first_for_a_never_collected_mandate`
- `test_get_invoices_for_export_returns_recurring_for_a_collected_mandate`
- `test_get_invoices_for_export_prefers_the_frozen_plan_over_the_mandate`
- `test_get_invoices_for_export_keeps_first_after_the_batch_is_submitted` — freeze `FRST`, then set
  `last_collected_at` and the batch's `submitted_at` to the same moment; assert the row still carries
  `SequenceType::First`. This is the regression guard for the trap.
- `test_get_invoices_for_export_uses_the_new_active_mandate_after_reauthorisation` — old row collected then
  revoked, new active row null → `First`.
- `test_get_collection_candidates_returns_the_resolved_sequence`
- `test_get_collection_candidates_prefers_the_frozen_plan`
- `test_get_collection_candidates_does_not_fan_out_on_mandate_history` (one active + one revoked row → one
  candidate)
- `test_freeze_sequence_types_stamps_first_and_recurring`
- `test_freeze_sequence_types_does_not_overwrite_an_existing_plan`
- `test_freeze_sequence_types_skips_a_refund`
- `test_freeze_sequence_types_skips_a_declined_invoice`
- `test_replan_sequence_types_clears_and_refreezes_the_batch`
- `test_replan_sequence_types_uses_the_current_active_mandate`
- `test_replan_sequence_types_rejects_an_exported_batch`
- `test_replan_sequence_types_rejects_a_submitted_batch`
- `test_replan_sequence_types_rejects_a_completed_batch`
- `test_replan_sequence_types_does_not_touch_another_batch`

### Feature — exporter

`tests/Feature/Infrastructure/Billing/Payment/SepaExportServiceImplTest.php` — the `createDebitInvoice` helper
gains `SequenceType $sequenceType = SequenceType::Recurring`:

- `test_export_creates_one_direct_debit_block_per_sequence_type` — one FRST + one RCUR invoice → 2 `pain:PmtInf`,
  one `SeqTp` each.
- `test_export_emits_only_the_frst_block_for_an_all_first_batch`
- `test_export_emits_only_the_rcur_block_for_an_all_recurring_batch`
- `test_export_uses_the_batch_transfer_date_on_both_direct_debit_blocks` — both
  `//pain:PmtInf/pain:ReqdColltnDt` equal `sepa_transfer_date`.
- `test_export_gives_each_direct_debit_block_a_unique_payment_information_id`
- `test_export_groups_each_invoice_into_exactly_one_block` — assert the FRST block's `EndToEndId` set and the RCUR
  block's set, and that no id appears twice.
- `test_export_emits_no_direct_debit_block_without_debit_invoices` (refunds and purchase orders only)
- `test_export_leaves_refunds_and_purchase_orders_in_the_credit_transfer_file` — debit blocks unchanged by a
  refund/PO in the same batch; existing credit-transfer tests stay green.

Use XPath (`//pain:PmtInf`, `./pain:PmtTpInf/pain:SeqTp`, `.//pain:EndToEndId`) on the returned XML, as the
existing tests already do. Assert `NOTPROVIDED`/`CdtrAgt` per `CODING_STANDARDS.md` for purchase orders.

### Unit — readiness service

`tests/Unit/Domain/Billing/Payment/Readiness/ExportReadinessServiceImplTest.php` (plan 02):

- `test_assess_passes_a_legitimate_first_collection`
- `test_assess_passes_a_legitimate_recurring_collection`
- `test_assess_reports_a_stale_frst_plan_as_broken_data` — frozen `FRST`, mandate stamped, batch unsubmitted →
  `SequenceTypeMismatch`, category `BrokenData`, blocked.
- `test_assess_reports_a_stale_rcur_plan_after_reauthorisation` — frozen `RCUR`, new active mandate null.
- `test_assess_does_not_report_the_sequence_of_a_presented_batch` — frozen `FRST`, mandate stamped,
  `submittedAt` set → no issue.
- update plan 02's `test_assess_reports_a_sequence_type_mismatch_as_broken_data` to build the mismatch through the
  frozen plan rather than two fakes that disagree arbitrarily.

### Feature — mandate readiness

`tests/Feature/Infrastructure/Billing/Payment/MandateReadinessServiceImplTest.php` (plan 04):

- replace `test_for_members_keeps_expected_sequence_type_recurring_until_plan_05` with
  `test_for_members_returns_first_for_a_never_collected_mandate` and
  `test_for_members_returns_recurring_for_a_collected_mandate`.
- construct with `new MandateReadinessServiceImpl(new MandateExpiry(...), new MandateSequenceResolver())`.

### Feature — reopen

`tests/Feature/Infrastructure/Billing/Invoices/InvoiceRepositoryDbTransitionTest.php` (plan 01):

- `test_reopen_clears_the_frozen_sequence_type`

### Feature — Filament

Extend `tests/Feature/Filament/SepaBatches/SepaBatchResourceTest.php`:

- `test_replan_sequence_action_is_visible_on_a_pending_unexported_batch`
- `test_replan_sequence_action_is_hidden_after_the_first_export`
- `test_replan_sequence_action_is_hidden_after_submission`
- `test_replan_sequence_action_is_hidden_without_update_permission`
- `test_replan_sequence_action_clears_and_refreezes_the_plan`
- `test_replan_sequence_action_unblocks_a_stale_export`

### Scenario — end to end

`tests/Feature/Scenarios/FrstRcurSequenceScenarioTest.php` (real repositories and services, fixed clock):

- `test_a_mixed_batch_exports_a_first_and_a_recurring_block` — `SepaBatchService::export` with a satisfied gate
  (valid IBANs, notice period met); parse the direct-debit XML.
- `test_re_exporting_a_submitted_batch_keeps_the_first_block` — close, export, `markSubmitted`, export again;
  assert `SeqTp` is still `FRST` and `last_collected_at` equals `submitted_at`.
- `test_the_next_batch_for_a_collected_mandate_is_recurring` — batch A submitted, then batch B closes and exports
  with `RCUR`.
- `test_a_reauthorised_mandate_starts_with_a_first_block` — revoke via plan 04's `MandateRepository::register`,
  close/export the next batch, assert `FRST` and the new reference.
- `test_a_declined_first_collection_makes_the_retry_recurring` — submit, decline (plan 01), reopen into a new
  Open batch, close/export, assert `RCUR`.
- `test_the_export_gate_does_not_block_a_legitimate_first_collection` — the readiness report from this path has no
  `SequenceTypeMismatch`.
- `test_replanning_a_stale_first_plan_unblocks_the_export` — freeze FRST, stamp the mandate from another submitted
  batch, assert the gate blocks, run `replanSequenceTypes`, then export successfully as RCUR.
- `test_replanning_after_reauthorisation_exports_the_new_mandate_as_first` — freeze RCUR, re-authorise the member,
  run `replanSequenceTypes`, assert FRST and the new reference in the XML.

## Hand-offs

- **Plan 04 (mandate lifecycle)** owns the versioned mandate, `last_collected_at`, the stamping at submission and
  `SepaExportInvoice::$paymentInformationId` / `$lastCollectedAt`. This plan never writes `last_collected_at` and
  never changes the submission path; it only reads the stamp once the batch is presented. It also replaces the
  literal `SequenceType::Recurring` in `MandateReadinessServiceImpl` with the resolver.
- **Plan 02 (readiness & submission)** owns `SequenceType`, the candidate/readiness comparison, the export gate,
  `recordExport` and `markSubmitted`. This plan fills the seam rather than replacing it:
  `SepaCollectionCandidate::$sequenceType` gets a real value, `SepaBatchExportContext` gains `submittedAt`, and the
  comparison gains the "not yet submitted" guard. Do not add a second readiness reason or a second gate.
- **Plan 01 (declined-payment recovery)** owns `InvoiceRepositoryDb::reopen`. It must clear
  `sepa_sequence_type` when it detaches an invoice, otherwise a retried invoice keeps the plan of the batch it left.
  Its decline path must continue to leave `last_collected_at` untouched (plan 04's rule), which is what makes the
  retry RCUR.
- **Plan 06 (manual payers)** keeps the stored `members.payment_method`. An invoice of a Transfer member without an
  active mandate is not exported; its frozen `FRST` is harmless and is cleared if the invoice is re-batched. The
  method is not a second sequence source.
- **Plan 03 (declined-SEPA notice)** is unaffected: it consumes plan 01's event and plan 04's
  `mandateNeedsReauthorisation`; the sequence is not part of the notice.

## Out of scope

- `FNAL` (final collection) and any "member is leaving" sequence handling.
- Automatic retries or automatic re-sequencing. Retries stay manual (plan 01), and a stale plan is not silently
  re-derived.
- Changing when `last_collected_at` is stamped, or stamping on payment success instead of submission.
- Submitting the file to the bank; submission remains plan 02's explicit manual action.
- An export history table or per-export audit of the sequence. The frozen value is the plan; re-exports repeat it.
- A UI column or filter for the sequence. The readiness report and the XML are the surfaces.
- Backfilling the frozen column for historical batches. Regression safety comes from `last_collected_at` (plan 04's
  backfill) plus the export-time freeze.

## Open questions

None. The three decisions this plan needed — freeze at close with an explicit re-plan action, keep the strict
`SequenceTypeMismatch` gate, and re-plan only before the first export — are settled above.

## Verification

```bash
./Taskfile artisan test --compact tests/Unit/Domain/Billing/Payment/MandateSequenceResolverTest.php
./Taskfile artisan test --compact tests/Unit/Domain/Billing/Payment/SepaExportInvoiceTest.php
./Taskfile artisan test --compact tests/Unit/Domain/Billing/Payment/SepaBatchServiceImplTest.php
./Taskfile artisan test --compact tests/Unit/Domain/Billing/Payment/SepaBatchServiceTest.php
./Taskfile artisan test --compact tests/Unit/Domain/Billing/Payment/Readiness/SepaBatchExportContextTest.php
./Taskfile artisan test --compact tests/Feature/Infrastructure/Billing/Payment/SepaBatchRepositoryDbTest.php
./Taskfile artisan test --compact tests/Feature/Infrastructure/Billing/Payment/SepaExportServiceImplTest.php
./Taskfile artisan test --compact tests/Unit/Domain/Billing/Payment/Readiness/ExportReadinessServiceImplTest.php
./Taskfile artisan test --compact tests/Feature/Infrastructure/Billing/Payment/MandateReadinessServiceImplTest.php
./Taskfile artisan test --compact tests/Feature/Infrastructure/Billing/Invoices/InvoiceRepositoryDbTransitionTest.php
./Taskfile artisan test --compact tests/Feature/Filament/SepaBatches/SepaBatchResourceTest.php
./Taskfile artisan test --compact tests/Feature/Scenarios/FrstRcurSequenceScenarioTest.php
./Taskfile stan
./Taskfile lint
```
