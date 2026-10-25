# SEPA & payment robustness gap inventory

**Date**: 2026-10-21
**Question**: Which SEPA and payment-robustness features does WSV Admin lack? (Research asset for the [missing-features map](../maps/20261021-missing-features/MAP.md); feeds judge ticket 04.)
**Method**: code audit only — every claim cites files read during this session. Complements the earlier [Rabobank SEPA automation analysis](20260611_rabobank-SEPA-batch-incasso-automation.md), whose recommendations (FRST/RCUR, mandate lifecycle, rejection reasons) are checked against what actually got built since.

---

## Summary

The monthly collection pipeline itself is solid and recently hardened: batch generation + progress widget, `sepa_transfer_date` (incassodatum) as the XML due date, purchase orders in the credit-transfer file, and two-transaction reversal matching that marks invoices/POs declined. The gaps sit around it: **the mandate is write-once at registration and has no lifecycle**, **a declined invoice is a dead end with no re-collection path**, **manual payers are an implicit afterthought (no flag, no due date, no cash/write-off marking)**, and **nothing validates a batch before export or records that it was submitted**. Escalation mechanics (due date, overdue aging, write-off) and online payment rails do not exist at all.

### Candidates proposed

| # | Candidate | Size | Gist |
|---|---|---|---|
| 1 | SEPA mandate lifecycle management | medium | Mandate data is captured once at registration and frozen; no state, no re-authorisation, no expiry tracking, no admin editing, reference invisible to everyone |
| 2 | FRST/RCUR sequence-type tracking | small | Exporter hardcodes `RCUR` for every direct debit; first collection under a new mandate must be `FRST` |
| 3 | Declined-payment recovery (re-collection run) | medium | `Declined` invoices can never re-enter a batch; the money is simply never collected |
| 4 | Manual payers as a first-class concept | medium | "Pays by transfer" is inferred from absence of a `payment_information` row; no due date, no cash/write-off marking without a fabricated bank transaction |
| 5 | Export readiness validation & submission tracking | small | No pre-export sanity checks (an empty IBAN silently ships); no record that a batch was actually submitted to the bank |
| 6 | Escalation mechanics for unpaid invoices (due date / overdue / write-off) | medium | No due date, no aging, no write-off; the reminder *emails* belong to the communication inventory, the payment mechanics live here |
| 7 | Online payment rail (iDEAL) | large | Nothing exists; would need a PSP contract + webhooks; mostly relieves the manual-payer and declined-payer pain |

### Checked and already covered (hypotheses refuted)

See the final section — notably: collection frequency limits, scheme (Core) configuration, creditor identifier handling, the declined/credited two-transaction matching itself, the transfer-date work, and the batch-created notification email.

---

## 0. Current state (the audit)

### 0.1 Batch generation and export

- **Generation**: `SepaBatchGeneratorImpl` (`app/Domain/Billing/Payment/SepaBatchGeneratorImpl.php`) creates the batch (`invoice_date` + `sepa_transfer_date`), attaches open invoices from the batch month, then dispatches one `GenerateInvoice` job per billable member, finishing with `FinishSepaBatchGeneration` + `SendSepaBatchCreatedEmail`. Scheduled monthly: `Schedule::command(GenerateSepaBatchCommand::class)->monthlyOn(1, '04:00')` in `routes/console.php`; default transfer date = invoice date + 14 days (`GenerateSepaBatchCommand::parseSepaTransferDate`). Also reachable via the Filament "SEPA ronde genereren" action (`GenerateSepaBatchAction`) and the create page (`CreateSepaBatch`, which honours the `attach_invoices` / `attach_purchase_orders` checkboxes).
- **Close**: `SepaBatchServiceImpl::closeBatch` marks invoices `Open → Pending`, overrides their `date` with the batch `invoice_date`, creates bookkeeping records, and dispatches `SepaBatchClosed` — which `QueueInvoiceEmails` turns into the invoice emails (the de facto pre-notification).
- **Export**: `SepaExportServiceImpl` (`app/Infrastructure/Billing/Payment/SepaExportServiceImpl.php`) builds pain.008 (direct debit: positive member invoices) and pain.001 (credit transfer: negative invoices = refunds, plus purchase orders = supplier payments). `EditSepaBatch::exportSepa` zips the two XMLs for download; the action is visible whenever the batch status is `Pending`.
- **Complete**: `SepaBatchServiceImpl::completeBatch` throws while any invoice in the batch is still `Open`/`Pending`; `Declined` invoices do **not** block completion.

### 0.2 Member mandate data

- `payment_information` (`2026_06_10_181456_create_payment_information_table.php`): one row per member (`member_id` unique), `banking_account_number`, `banking_bic`, `banking_account_holder_name`, `mandate_accepted_date` (**NOT nullable**), soft deletes.
- The **mandate reference is derived, not stored**: `MandateId` formats `C{memberId:06d}-{paymentInformationId:06d}` (`app/Domain/Billing/Payment/MandateId.php`). It is emitted into the XML but never shown in any admin screen or member mail.
- **Registration is the only place a mandate is captured** — `StorePaymentInformationRequest` requires IBAN + BIC + holder + `mandate_accepted` (`accepted` rule), and `NewMemberService::toNewMember` throws without a mandate date; `MemberDbRepository::newMember` persists it.
- The admin member form (`MemberForm` payment tab) can edit IBAN/BIC/holder (none required), but `mandate_accepted_date` is `disabled()->dehydrated(false)` — immutable in the UI. A member without a `payment_information` row cannot be onboarded: the Filament `Grid::relationship('paymentInformation')` auto-creates the related row from dehydrated fields only, so an insert would violate the not-null `mandate_accepted_date`. (The tab also renders a `uuid` "reference" field — no such column exists; leftover from the pre-rename design documented in the June analysis.)
- **No mandate lifecycle is modelled**: no state (active/revoked/expired), no last-used date, no expiry after 36 months of non-use, no re-authorisation flow, no sequence-type tracking. Grep for `expire|expiry|reauthor|last_used|36 months` across `app/` returns nothing.

### 0.3 Declined / credited payment handling (built Aug 2026)

- `banking_transactions.reversed_by_transaction_id` self-FK (`2026_08_04_000000_add_reversal_columns_to_banking_transactions.php`); reversal pairs are auto-detected (`BankTransactionDbRepository::findReversalMatch`: opposite amount ±0.01, same counterparty + description, 56-day window) during `MatchBankTransactionsCommand` (hourly), with manual link/unlink actions on the transaction view page.
- `BankTransactionServiceImpl::linkReversal` copies attached references to the reversal transaction and marks attached invoices `Declined` and POs payment-declined, then resolves both transactions; `unlinkReversal` reverses the marking. This is working and tested (`tests/Feature/Scenarios/PaymentMatchingScenarioTest.php`, `SepaExportServiceImplTest`, etc.).
- What happens *after* the decline is the gap — see candidate 3.

### 0.4 Members who do not pay by direct debit

- **How one becomes a manual payer**: implicitly, by having no (soft-deleted) `payment_information` row. Registration always captures one, so manual payers arise only through admin-side removal, legacy data, or by blanking the (not-required) banking fields — which leaves a row with an empty IBAN (see candidate 5).
- **Invoices**: generated identically for every billable member (`InvoiceGeneratorImpl` is payment-info-agnostic; `BillableItemsViewRepository::listBillableMembers`). At batch close all invoices go `Pending`.
- **Communication**: `InvoiceMailRepositoryDb` sets `sepaTransferDate` only when the member has a `payment_information` row; `resources/views/mail/invoice.blade.php` then renders "Het bedrag wordt automatisch geïncasseerd op …" for SEPA payers and "Het bedrag dient u over te maken naar …" (transfer to the club IBAN, quoting the invoice number) for manual payers — with **no payment deadline**. Refunds (negative invoices) promise a payback to the member's known IBAN.
- **Marking paid**: either auto-matching (`TransactionMatchingServiceImpl` → `InvoiceRepositoryDb::findMatchingCredit`, IBAN + amount ±0.01 + date ±30 days) or the manual `AttachBankTransactionAction` from the invoice/PO side, then `BankTransactionDbRepository::complete` marks paid. `InvoicePolicy::markPaid` additionally **requires at least one attached bank transaction** — so a cash payment, a foreign transfer, or a written-off amount cannot be marked paid without first fabricating a bank transaction (possible via `CreateBankTransaction`, at the cost of polluting bank reconciliation data).

### 0.5 Collection configuration

- `config/sepa.php`: `creditor_id`, `creditor_name`, `creditor_iban`, `creditor_bic` (env-only), bound to `SepaConfiguration` in `AppServiceProvider::register` and consumed by the exporter and the invoice mail. No scheme switch — `digitick/sepa-xml` v3.1 is always driven as Core (`TransferFileFacadeFactory::createDirectDebit`), sequence type fixed to `PaymentInformation::S_RECURRING` per batch (line 69 of `SepaExportServiceImpl`).
- Frequency is implicitly governed by the billing model: one open invoice per member per month (`InvoiceRepositoryDb::applyLines` uses `firstOrCreate` on open invoice + month), and an invoice belongs to at most one batch (`sepa_batch_id` FK, `whereNull` guards in `addOpenInvoicesFromBatchMonth` / the `addInvoices` action options).

### 0.6 Online payment rails

- Nothing. Grep for `ideal|mollie|stripe|checkout|buckaroo` across `app/` returns nothing; the registration flow has no payment step; the only inbound money channel is the MT940 import.

### 0.7 Escalation for unpaid invoices

- Nothing. No `herinnering`/`aanmaning`/reminder anything in code; the only re-touch capability is the manual per-invoice resend action (`InvoicePolicy::resendEmail`, Pending only). Invoices have no due date at all — for SEPA payers the incassodatum plays that role implicitly; for manual payers there is no deadline and therefore no "overdue" notion, no aging view (the list filter `outstanding` in `InvoicesTable` is the closest), and no write-off/handover state.

---

## 1. Candidate: SEPA mandate lifecycle management (medium)

**What exists today.** One `payment_information` row per member with a frozen `mandate_accepted_date` (§0.2); a derived mandate reference (`MandateId`, format `C000042-000007`); the registration checkbox as the only authorization event. The June analysis ([Rabobank SEPA automation](20260611_rabobank-SEPA-batch-incasso-automation.md)) already recommended mandate state, `last_collection_date`, and a `MandateSequenceResolver`; none of that was built.

**What is missing.**

- An admin-visible mandate record: the mandate reference, signed date, and state are shown nowhere in the panel (the member form only shows the immutable date; the `uuid` field is a dead leftover).
- Any state beyond "exists": revocation (member withdraws authorization — today the only move is soft-deleting the row, which also discards the IBAN needed for refunds), and expiry after **36 months of non-use** (SEPA Core rulebook: an unused mandate lapses and must be re-authorised — a dormant member returning after 3+ years would be collected on a lapsed mandate).
- A re-authorisation flow: recording a newly signed mandate (new date, possibly new IBAN, ideally a new reference so the first new collection is `FRST` again). There is no working admin path to even *create* payment info for a member who lacks it (§0.2, not-null constraint).
- Pre-notification content: the EPC recommends the pre-notification include the mandate reference and creditor identifier; the invoice mail (`invoice.blade.php`) shows only the incassodatum for SEPA payers. (The mail itself belongs to the communication inventory — the *data* belongs here.)
- The signed mandate document is never captured or stored; retention duty (14 months after last collection) is unverifiable.

**Pain relieved.** Finance cannot answer "is this member's mandate still valid?", cannot fix a bouncing mandate without a workaround, cannot formally revoke an authorization, and cannot evidence mandate compliance in an audit. Dormant members (a real pattern in a seasonal club) silently roll onto lapsed mandates.

**Rough size.** Medium: mandate state column(s) (`status`, re-authorisation creates a new dated mandate reference; `last_collected_at` can be set at batch close), member-form work (editable/creatable, remove the dead `uuid`), a mandate panel on the member view, and expiry computed from `last_collected_at`.

## 2. Candidate: FRST/RCUR sequence-type tracking (small)

**What exists today.** `SepaExportServiceImpl` sets one payment-information block per batch with `seqType => PaymentInformation::S_RECURRING` — every direct debit is declared recurring, always (line 69). The library supports `S_FIRST`, `S_FINAL`, `S_ONEOFF` too.

**What is missing.** The first collection under a new mandate must be flagged `FRST`; subsequent ones `RCUR` (and `FNAL` for the last collection when a member leaves). There is no tracking of whether a mandate has been collected before — neither a stored flag nor a derived query exists.

**Pain relieved.** Debtor banks may return or delay a mis-flagged first collection (`MD02`/`MS03` class problems); Rabobank tolerates it in practice but it is a scheme violation waiting to bite on new mandates. FRST also triggers the debtor bank's mandate-verification flow, which is how IBAN errors get caught early.

**Rough size.** Small: decide the source of truth (store `last_collected_at` on `payment_information` at batch close, or derive "has a prior direct debit" from prior batches' paid invoices), then split the export into a `FRST` and a `RCUR` payment-information block. Pairs naturally with candidate 1's re-authorisation (new mandate → `FRST` again).

## 3. Candidate: Declined-payment recovery / re-collection run (medium)

**What exists today.** Reversal detection and marking (§0.3) leaves the invoice at `Declined`. `InvoiceStatus` (`Open`, `Pending`, `Paid`, `Declined`) has no exit from `Declined` in the UI: `InvoicePolicy::markPending` only allows `Open → Pending`; the batch "add invoices" action only offers invoices with `sepa_batch_id IS NULL` **and** status `Open` (`EditSepaBatch::addInvoices`); `addOpenInvoicesFromBatchMonth` likewise requires `whereNull('sepa_batch_id')`. The only code path that revives a declined invoice is `BankTransactionServiceImpl::unlinkReversal` → `markAsPending` (repository update without a status guard) — i.e. undoing the decline marking entirely, which requires the reversal link and un-resolves the bookkeeping. Purchase orders, by contrast, got `reopenPayment` (`PurchaseOrderRepository::reopenPayment`, `PurchaseOrderPolicy::reopenPayment`) from the Oct-18 plan — but it is not exposed in `PurchaseOrderService` or any Filament action.

**What is missing.** A deliberate re-collection path: reopen a declined invoice into the next batch (new `sepa_batch_id`, back to `Pending`, guarded against double collection), and a bulk "retry declined payments of batch X" action. Optionally a reason capture (AM04 insufficient funds vs AC01 wrong IBAN — the June analysis lists the codes), because the retry policy differs: retry as-is vs fix mandate data first (candidate 1).

**Pain relieved.** Today every declined direct debit is manually chased outside the system — the invoice sits `Declined` forever, the club never collects, and nothing in the panel distinguishes "declined, needs contact" from "declined, settled later". Even the natural self-heal — the member pays by manual transfer after all — is blocked by policy once the invoice is `Declined`: `InvoicePolicy::attachBankTransaction` requires status `Pending`, so the incoming transaction cannot be attached to the declined invoice.

**Rough size.** Medium: a reopen transition (policy + service + repository + tests), re-batch capability for declined invoices, and the bulk action; PO `reopenPayment` exposure is a small add-on.

## 4. Candidate: Manual payers as a first-class concept (medium)

**What exists today.** Everything in §0.4: invoices generate normally, the mail contains transfer instructions, matching works, `markAsPaid` exists — but "manual payer" is never a decision, only the absence of a row, and the IBAN fields on the member form are optional so a half-emptied row silently switches the mail text while shipping an empty IBAN into the export.

**What is missing.**

- An explicit payment method on the member (SEPA vs transfer), so the choice is visible, intentional, and reportable ("how many members do we collect vs chase?").
- A due date for transfer invoices — today's mail asks for payment with no deadline, which is also the precondition for any aging/overdue logic (candidate 6).
- A paid-marking path that does not require a bank transaction (cash at the clubhouse, a written-off remainder, or a foreign channel), instead of fabricating a `BankTransaction` via `CreateBankTransaction` and polluting reconciliation; `InvoicePolicy::markPaid`'s `bankTransactions()->doesntExist()` guard would need a guarded exception with a reason.
- A coherent story for refunds to manual payers (credit invoices for members without an IBAN cannot be paid out through the credit-transfer file — they fall out of the export's inner join and must be handled off-system).

**Pain relieved.** Finance gets a real answer to "who pays how", a deadline to chase against, and an honest cash/write-off path. The reminder *mails* for this group belong to the communication inventory — this candidate is the mechanics they hang on.

**Rough size.** Medium: payment-method column + member form/visibility, invoice due date (migration + mail text + policy), a transaction-less paid marking with reason, credit-invoice handling for IBAN-less members.

## 5. Candidate: Export readiness validation & submission tracking (small)

**What exists today.** `EditSepaBatch::exportSepa` zips whatever `SepaExportServiceImpl` produces; the only gates are the batch status (`Pending`) and the form validation `sepa_transfer_date >= invoice_date`. `getInvoicesForExport` inner-joins `member.paymentInformation` but never checks the IBAN is present/valid; `digitick/sepa-xml` performs no content validation either (`CustomerDirectDebitFacade::addTransfer` passes `debtorIban` straight through, and `BaseCustomerTransferFileFacade` only validates the due date). Nothing records that an export was downloaded, and nothing records that the file was actually uploaded to Rabobank.

**What is missing.**

- A pre-export readiness check: every to-be-collected invoice has a non-empty, well-formed IBAN (+ BIC) and mandate data; expired/to-be-flagged-FRST mandates surfaced; a report of invoices *excluded* from the export (manual payers — fine — vs broken rows — not fine). Today a single cleared IBAN row ships an empty `<DbtrAcct>` into pain.008 and the bank rejects the whole file at upload.
- A lead-time guard: the invoice mails (the pre-notification) are sent at close time while the incassodatum can be arbitrarily close — nothing warns when the notice period falls short of the 14-day EPC default (or whatever the club agrees in its terms).
- A submission record: `exported_at` / `submitted_at` (+ who) on `sepa_batches`, so "klaar voor export → wacht op export naar de bank" (the existing dashboard widget labels) reflects reality instead of memory. Re-downloading is currently unlimited and unlogged.

**Pain relieved.** The failure mode moves from "Rabobank rejects the file, mid-month, and finance has to dissect the XML" to "the panel refused and told me which member to fix". The submission record closes the loop on the only manual step left in the pipeline.

**Rough size.** Small: a validation service run from the export action (and/or at close), an exclusion report, one or two timestamp columns. The lead-time check is a day of work once the agreed notice period is decided.

## 6. Candidate: Escalation mechanics for unpaid invoices — due date / overdue / write-off (medium)

**What exists today.** Nothing (§0.7): no due date on invoices, no overdue notion, no aging view, no write-off. The status model ends at `Open / Pending / Paid / Declined`; the only chasing tool is a manual resend of the identical invoice mail.

**What is missing** (payment mechanics only — the herinnering → aanmaning *mail sequence* is scoped to the communication inventory):

- A due date per invoice (for SEPA payers the incassodatum; for manual payers the explicit deadline from candidate 4) and a computed overdue age / filter on the invoices list ("outstanding > 30/60/90 days").
- A write-off action/state (`afschrijven`) distinct from `Declined` — bookkeeping-visible, with a reason — for amounts the board decides not to pursue; currently the only terminal states are Paid and Declined (which means "the bank reversed it", not "we gave up").
- Optional: a handover marker (incassobureau / juridische stap) for the rare chronic case; probably overkill for a vereniging, listed for the judge to weigh.

**Pain relieved.** Without a due date and overdue aging, "who still owes us what, for how long" is manual spreadsheet work; without write-off, the books accumulate ghost receivables or misuse `Declined`. This candidate is the substrate the communication inventory's reminder sequence needs to trigger against.

**Rough size.** Medium: due-date column + population logic, aging filters/columns, write-off status + action + bookkeeping treatment. The mail sequence itself is deliberately out of this candidate.

## 7. Candidate: Online payment rail — iDEAL (large)

**What exists today.** Nothing (§0.6) — no PSP, no checkout, no payment links; the only inbound channel is MT940 import.

**What is missing.** A PSP integration (Mollie/Buckaroo are the usual Dutch suspects): an iDEAL payment link in the invoice mail (and possibly for the registration fee), a webhook that attaches the resulting transaction and marks the invoice paid, and refund support through the same rail.

**Pain relieved.** Manual payers pay in one tap instead of a manual transfer + matching dance; declined-payers can self-heal immediately after a reversal (pairs with candidate 3); the registration fee could be settled at signup instead of riding the first invoice.

**Rough size and honest caveats.** Large: an external contract and dependency (against the repo rule of not adding dependencies without approval — a decision for the user), a webhook surface, and reconciliation semantics for PSP-settled payouts (they arrive as one lump-sum transaction, not per-member). For a club with ~monthly billing and a strong direct-debit base, the value concentrates on the manual-payer minority; proposed for completeness, likely to lose the prioritization against candidates 1–6.

---

## Checked and already covered (hypotheses the code refutes)

- **Collection frequency limits** — nothing to build: SEPA Core imposes no scheme-level frequency limit to enforce; the billing model already guarantees at most one invoice per member per month (`InvoiceRepositoryDb::applyLines` `firstOrCreate`) and one batch per invoice (`sepa_batch_id` guards in `SepaBatchRepositoryDb::addOpenInvoicesFromBatchMonth` and `EditSepaBatch::addInvoices`).
- **Scheme configuration (Core vs B2B)** — Core is hardcoded via `TransferFileFacadeFactory::createDirectDebit`, which is the only sensible scheme for a vereniging collecting from consumers; B2B would require a separate bank contract and different mandate texts. No gap.
- **Creditor identifier handling** — exists: `config/sepa.php` (`SEPA_CREDITOR_ID` etc.) → `SepaConfiguration` (bound in `AppServiceProvider`) → exporter + invoice mail. Env-only static organisation data is acceptable; a settings UI would be polish, not robustness.
- **Declined/credited two-transaction matching and marking** — built and covered (Aug 2026): reversal detection (`findReversalMatch`), `linkReversal` marking, manual link/unlink, `unmatched_amount` zeroing for reversals. The remaining gap is only the recovery *afterwards* (candidate 3).
- **Transfer date / incasso datum work** — landed (Oct 2026): `sepa_transfer_date` column, exporter `dueDate`, invoice-mail collection date, invoice-date override at close (plan `20261006_invoice-batch-dates.md`, verified in `SepaBatchRepositoryDb` and `InvoiceMailRepositoryDb`).
- **Purchase orders and member declarations in the SEPA export** — landed for POs (`getPurchaseOrdersForExport`, `SepaExportPurchaseOrder`, plan `20261017`); declarations ride purchase orders (Sept 2026). Also explicitly excluded from this effort by the map.
- **Description-based invoice matching with optional IBAN** — the open `TODO.md` item; excluded from this effort by the map.
- **Batch-created notification to finance** — exists: `SepaBatchGeneratorImpl` chains `SendSepaBatchCreatedEmail` after generation, delivering `SepaBatchCreatedMail` to the financial administration recipient.
- **Batch generation progress visibility** — exists: generation timestamps + `SepaBatchGenerationProgress` / `BatchStatsOverview` widgets (the earlier TODO item, closed).

## Small hygiene observations (no candidate, worth fixing in passing)

- `MemberForm` renders a `uuid` "reference" field on the payment tab; the `payment_information` table has no such column (leftover from the pre-`MandateId` design documented in the June analysis).
- `SepaBatchGeneratorImpl::generate` always attaches the batch-month invoices but never the month's purchase orders — the PO attach path exists only via `CreateSepaBatch` (checkbox) or the manual "add purchase orders" action; the console/scheduled run therefore never sweeps POs into the monthly batch.

## Seams with the other inventories (for the judge tickets)

- Communication inventory (ticket 03): the herinnering/aanmaning *mail sequence*, declined-payment notice mail, and any "payment arranged" correspondence. This inventory owns the due date / overdue / write-off mechanics they trigger against (candidate 6) and the manual-payer mechanics (candidate 4).
- Financial reporting inventory (ticket 02): write-off bookkeeping treatment (candidate 6) and the aging/outstanding views overlap with receivables reporting; decide the split there.
