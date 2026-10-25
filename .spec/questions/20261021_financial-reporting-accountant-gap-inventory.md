# Financial reporting & accountant gap inventory

Date: 2026-10-21
Feeds: [Missing features audit map](../maps/20261021-missing-features/MAP.md), ticket 02 (financial reporting & accountant candidates) — input for the judge ticket.

## Summary

WSV Admin has a functioning **single-entry, cost-center bookkeeping core**: every invoice, purchase order (which also carries member declarations via `member_id`), and matched bank transaction produces `BookkeepingRecord` rows with a price/VAT split, linked to a cost center and (once collected) to a bank transaction; MT940 statements are imported with per-statement integrity and chain validation since August 2026. But **everything this core produces is only visible on screen, one table at a time — there is no export, no report document, and no year-end flow**: the sole file export in the entire application is the SEPA XML zip (`SepaExportService`). The book-year is a bare integer, opening balances have a data model but no UI, and the treasurer currently serves the accountant / ALV / jaarvergadering by retyping or screenshotting admin tables.

Proposed candidates (rough size, ordered by expected value for the finance operator):

1. **A — Year-end financial report per book year** (cost-center results with a baten/lasten split, printable/exportable) — small/medium.
2. **B — Accountant export** (journal/bookings CSV, or a Dutch package import format) — medium (large if package-specific).
3. **C — Openstaande posten: debtor overview per member** (open count/amount, oldest item, aging) — small.
4. **D — Bank account balance overview + opening-balance entry UI** (per account per year; fixes account-blind running totals) — small/medium.
5. **E — Book-year closing flow** (carryover of opening balances, closed-year guard, year-end checklist) — medium.
6. **F — VAT (btw) totals per period** (plus a VAT-model sanity check with the treasurer) — small for the view; medium if the VAT model needs a rate/exemption concept.
7. **G — ANBI annual financial overview** — small if it reuses A's export; confirm ANBI status first.
8. **H — Year-end reconciliation checklist** (consolidated "is this year complete" report) — small; clusters naturally with A/E.

Refuted or already covered (do not propose): strict trial balance / proefbalans (requires the explicitly-rejected grootboek redesign; the cost-center equivalent already exists), budget-vs-actual per cost center (exists on screen, improvements folded into A), bank reconciliation *mechanics* (exist; the gap is aggregation — D/H), and the map-excluded items (description-based matching, SEPA-export TODOs, member portal) which are not proposed.

---

## 1. Current state audit

### 1.1 Bookkeeping records — what is recorded and how it links

- Schema (`database/migrations/2026_06_22_054611_create_bookkeeping_records_table.php`): `year`, `cost_center_id`, `amount_price` + `amount_vat` (decimal 10,3), `description`, nullable polymorphic `reference` (`Invoice` | `PurchaseOrder`), plus `bank_transaction_id` (nullable FK, added 2026-07-05). Model: `app/Models/BookkeepingRecord.php` (`costCenter()`, `bankTransaction()`, `reference()` morphTo, `amount` as `CompoundPrice`, `unattached()` scope).
- Creation paths, all in `app/Infrastructure/Bookkeeping/BookkeepingRecords/BookkeepingRecordDbRepository.php`:
    - **Batch close**: `SepaBatchServiceImpl::closeBatch()` → `createForBatch()` — one record per invoice per cost center for the batch's pending invoices; `year` from the batch invoice date. Purchase-order lines book as **negative** amounts (`SUM(-price)`), invoice lines as positive — so a record's sign encodes baten/lasten.
    - **Transaction completion**: `BankTransactionDbRepository::complete()` → `createForInvoice()` + `createForPurchaseOrder()` + `linkToBankTransaction()`; marks matched invoices/POs paid. Refuses with `CouldNotCompleteTransaction` when `|unmatched_amount| >= 0.01` — this is the enforcement that a completed transaction's documents are fully covered and their records linked.
    - **Manual**: `BookkeepingRecordResource` create page (`BookkeepingRecordForm`), `CreateBookkeepingRecordFromTransactionAction`, and attach/detach actions (`AttachBookkeepingRecordAction`, `AttachBankTransactionAction`).
- **Declarations**: there is no separate Declaration entity. Declarations are purchase orders with a `member_id` (`2026_09_24_091647_add_member_id_to_purchase_orders_table.php`; Dutch labels render them as "Declaratie", `lang/nl/labels.php:396-407`), so they flow through the same PO → bookkeeping path. (The dedicated-domain proposal in `20260923_member-declarations-implementation.md` was not built.)
- **The "every record has an attached bank transaction" check**: implemented as (a) the `complete()` refusal above, and (b) the view-page relations — `BookkeepingRecordInfolist` shows the linked invoice/PO and bank transaction as clickable links (TODO.md item 3, closed by `.spec/plans/20260808_bookkeeping-record-relations-view.md`). There is **no list-level surface** of the inverse: `BookkeepingRecordsTable` has no bank-transaction column, filter, or widget flagging records with no linked transaction (they exist legitimately between batch close and collection, and after a reversal unlinks them — `BankTransactionDbRepository::linkReversal()` nulls `bank_transaction_id` on the original's records).

### 1.2 Cost centers, budgets, and what year-end reporting produces today

- `CostCenter` (number/title/description, `app/Models/CostCenter.php`); `CostCenterBudget` (`year`, `starting_amount` "Startbedrag", `budget_amount` "Budget" — added 2026-09-30, unique per cost center per year; `app/Models/CostCenterBudget.php`). Managed via `CostCenterBudgetsRelationManager` (CostCenter edit page) and the bulk `CreateCostCenterBudgetsAction` → `CostCenterBudgetRepository::createZeroBudgetsForYear()` on the cost-center list page.
- `CostCenterResults` page (`app/Filament/Admin/Pages/CostCenterResults.php`, in the Bookkeeping cluster): year selector (years = distinct record years ∪ budget years ∪ current year), then per cost center: starting amount, budget, total, closing balance (starting + total), budget difference (total − budget), with footer sums. It is a plain on-screen table — **no header actions, no export, no print view, no PDF, no CSV**.
- Two accuracy nuances: the `total_amount` column is labeled "Omzet" (`labels.revenue`, `lang/nl/labels.php:392`) but is actually the **net** sum of all bookkeeping records (positive invoice records *and* negative PO records) — i.e. "mutations", not revenue; and the page ignores VAT entirely, even though the domain repository (`BookkeepingRecordDbRepository::getResultsForYear()`) already returns `total_vat` inside the `CompoundPrice` `CostCenterYearResult` DTO.

### 1.3 Book years, opening balances, closing

- **No book-year entity.** A book year is a bare `year` integer on `bookkeeping_records`, `cost_center_budgets`, and `bank_account_year_balances`; "book year" selectors are derived from data (`CostCenterResults::getAvailableYears()`, `book_year` filters in `InvoicesTable`, `PurchaseOrdersTable`, `BankTransactionsTable`/`BookYearFilter`). Nothing marks a year as closed; records and budgets of past years stay fully editable and deletable.
- **Opening balances exist as a data model only.** `bank_account_year_balances` (2026-08-22 migration) + `BankAccountYearBalance` model + `BankAccountRepository::getOpeningAmount()` — but: there is **no Filament UI to enter them** (the `BankAccountResource` has no year-balance relation manager or form section; `app/Filament/Admin/Resources/BankAccounts/`), `getOpeningAmount()` is **called from nowhere** in the app, and the label `expected_closing_balance` (`lang/nl/labels.php:363`) is defined but unused. The per-account balance overview ("`BankAccountBalanceOverview`, produced by `BankAccountBalanceRepository`") described in `.agents/docs/bookkeeping/CONTEXT.md` **does not exist in code** — it is the unbuilt Phase 2 of the multiple-bank-accounts plan (`.spec/plans/20260822_multiple-bank-accounts-bookkeeping.md` §7).
- **No year-end closing flow**: no action carries a year's closing results into next year's starting amounts, and no checklist verifies a year is complete before closing.

### 1.4 Exports for an accountant

**None.** The only file export in the application is the SEPA XML zip (`EditSepaBatch::exportSepa()` → `SepaExportService`; `app/Filament/Admin/Resources/SepaBatches/Pages/EditSepaBatch.php:157-182`). A repo-wide search finds no CSV/Excel/PDF/ExportAction anywhere in `app/`; `composer.json` contains no export or PDF package (no Filament export plugin, no dompdf/snappy). No general ledger (grootboek), no journal (dagboek) export, no trial balance (proefbalans), no auditfile/SBR, no SnelStart/eBoekhouden/Exact connector. Note: a full grootboek/double-entry model was explicitly considered and rejected in `.spec/questions/20260822_multiple-bank-accounts-bookkeeping.md` (Solution 3) — the system is deliberately a single-entry cost-center projection.

### 1.5 VAT (btw) handling

- VAT is carried as an **amount** on every line: `billable_items.vat`, `invoice_lines.vat` (auto-filled `price * 0.21` in `InvoiceForm`'s lines repeater and `BillableItemSection::VAT_RATE`), `purchase_order_lines.price_vat` (auto-suggested `round(price * 0.21, 2)` in `PurchaseOrderForm`, manually editable), and `bookkeeping_records.amount_vat` (carried through by every `createFor*` query in `BookkeepingRecordDbRepository`).
- There is **no VAT rate field and no exempt/zero-rate concept** — 21% is hard-coded in the admin forms and seeders — and **no VAT reporting**: no per-period totals, no VAT column in `CostCenterResults`, nothing for an aangifte.
- Domain nuance for the judge: a Dutch sports vereniging typically charges largely VAT-exempt contributions (the sports arrangement / art. 11 lid 1 Opw. btw, or KOR). If the recorded 21% VAT is fictional bookkeeping noise, VAT reporting would mislead; if the club genuinely charges VAT (e.g. bar, verhuur), it files aangifte and needs per-period totals. This must be confirmed with the treasurer before building anything (candidate F).

### 1.6 Open items (openstaande posten), debtors/creditors

- Debtors: `InvoicesTable` has an `outstanding` status filter (Open+Pending) + book-year filter; `OpenInvoicesWidget` shows aggregate open count/amount + a 12-month *invoiced* chart; `MyOpenInvoicesWidget` is per-admin. Per member: `InvoicesRelationManager` on the member view lists that member's invoices **without totals**. Batch level: `SepaBatch::openInvoiceTotal` in `BatchStatsOverview`.
- **Missing**: an aggregated per-member debtor overview (member → open count, open amount, oldest open invoice), aging buckets, any export for payment follow-up. Also structural: invoice status is updated in place, so historical outstanding positions cannot be reconstructed later (explicitly noted in `OpenInvoicesWidget` docblock, lines 72-74) — aging reports must be snapshots or derive from bank-transaction dates.
- Creditors: symmetric `OpenPurchaseOrdersWidget` + outstanding filter for purchase orders/declarations; no per-creditor aggregate either.

### 1.7 Bank reconciliation and balance-over-time

- Per **statement**: integrity status (opening + in/out vs closing), chain status vs the previous statement, matched percentage — `BankStatementsTable`/`BankStatementInfolist`, `DetermineBankStatementChainStatusAction`, `DetermineChainStatus` domain service, `CompleteAllMatchedAction`.
- Per **transaction**: `matched_amount`/`unmatched_amount` accessors (`app/Models/BankTransaction.php`), status + resolve status, `UnresolvedBankTransactionsWidget` on the dashboard, hourly auto-matching (`routes/console.php` → `MatchBankTransactionsCommand` → `MatchBankTransactionsJob`).
- **Missing, and one regression vs the plan**: the aggregate view. `RunningTotalSummery` and the month `Sum` summarizer are **account-blind** — they total across *all* bank accounts, mixing savings with checking, and `BankTransactionsTable` has **no bank-account filter** (the 20260822 plan's Phase 1 "account filter + per-account running totals" was never finished). The per-account year overview (opening + bij − af = expected closing, last statement date, chain status) was never built, and nothing charts the balance over time even though statements have carried monthly closing balances since August 2026.

---

## 2. Candidate gaps

### Candidate A — Year-end financial report per book year (small/medium)

- **Today**: `CostCenterResults` (see 1.2) — correct numbers, on-screen only, net-only, no VAT, "Omzet" mislabel on a net column.
- **Missing**: an exportable/printable year-end artifact: per cost center the starting amount, **baten** (invoice-sourced records) and **lasten** (PO-sourced records) split, net result, budget vs budgeted difference, VAT column, with totals — as a printable Blade view / PDF and/or CSV.
- **Pain relieved**: the treasurer currently retypes or screenshots admin tables for the accountant, the ALV, and the jaarvergadering. A board of a vereniging must produce a staat van baten en lasten within 6 months after the book year (art. 2:10 lid 2 BW, per the legal analysis in `.spec/questions/20260822`); the data is present, the document is not.
- **Size**: small/medium — the split is one `SUM(CASE …)` on `reference_type` over existing columns (the sign already encodes direction); the export needs a print/PDF or CSV decision (PDF adds a dependency decision — see constraints in AGENTS.md about dependency changes requiring approval).

### Candidate B — Accountant export (medium; large if package-specific)

- **Today**: no export of any bookkeeping data (1.4). All source data exists: per bookkeeping record the year, cost center, description, gross and VAT amounts, reference to invoice/PO, and (via `bank_transaction_id`) the counterparty description and date.
- **Missing**: an export of bookings (journal-like flat list per book year) that an accountant can import. Options for the judge/user: a generic CSV (date, description, cost center, amount incl./excl. VAT), a SnelStart/eBoekhouden/Exact import format, or an auditfile-style XML. Since the project explicitly keeps a single-entry cost-center model (rejected grootboek), the export would map cost centers to the accountant's cost-center/budget lines — the target package and mapping must be chosen with the user.
- **Pain relieved**: today everything the accountant receives is manual re-entry; this is the recurring yearly handshake.
- **Size**: medium for a generic CSV (query + download action + tests, pattern: `SepaExportService` + `EditSepaBatch::exportSepa`); medium/large for a package-specific format.

### Candidate C — Openstaande posten: debtor overview per member (small)

- **Today**: outstanding filter + aggregate widget + per-member invoice list without totals (1.6).
- **Missing**: a "debiteuren" page/table: one row per member with open invoice count, open amount, oldest open invoice date; optional aging buckets (< 30 / 30-90 / > 90 days); optional CSV for follow-up. (Historical aging needs a snapshot or bank-date derivation — statuses mutate in place.)
- **Pain relieved**: answering "who owes us money and since when" currently requires clicking through every member; follow-up on unpaid/declined members is manual.
- **Size**: small — one aggregated Filament page/relation table over `invoices` + `invoice_lines`.

### Candidate D — Bank account balance overview + opening-balance entry UI (small/medium)

- **Today**: `bank_account_year_balances` table + `getOpeningAmount()` exist but are unreachable from the UI (1.3); running totals are account-blind (1.7); `expected_closing_balance` label unused.
- **Missing**: (a) a UI to enter per-account yearly opening balances (relation manager on `BankAccountResource`, mirroring `CostCenterBudgetsRelationManager`); (b) a per-account overview per book year — opening amount, inflow, outflow, expected closing balance, last statement date, chain/integrity status; (c) per-account scoping: a bank-account filter on `BankTransactionsTable` and account-scoped month/running totals; (d) optionally a balance-over-time chart from statement closing balances.
- **Pain relieved**: the completeness assurance the 20260822 analysis was built around (opensaldo + mutations = saldo, per account, per year) cannot actually be performed anywhere in the UI today; mixing accounts makes the current running totals misleading.
- **Size**: small/medium — mostly read-side queries over existing tables plus one relation manager; also reconcile the stale `BankAccountBalanceRepository` references in `.agents/docs/bookkeeping/CONTEXT.md` with reality.

### Candidate E — Book-year closing flow (medium)

- **Today**: per-year integer book years; budgets zero-fillable per year; no closing, no carryover, no lock (1.3).
- **Missing**: a "close book year N" action that (1) verifies completeness (see candidate H), (2) carries each cost center's closing balance into year N+1's `starting_amount` and writes the bank-account year balances, and (3) marks N closed — with a guard (Filament policy/service check) against editing/deleting records and budgets in closed years.
- **Pain relieved**: year-end is manual re-entry today, and nothing prevents editing a prior year after its numbers were approved at the ALV — a real audit/compliance risk for a vereniging board.
- **Size**: medium — a status/closing service + migration (closed-year marker) + policy guards across Bookkeeping/Billing resources.

### Candidate F — VAT (btw) totals per period (small view; medium if the model changes)

- **Today**: VAT amounts on every record, 21% hard-coded, no aggregation anywhere (1.5).
- **Missing**: a per-period (month/quarter/year) VAT totals view over `bookkeeping_records` (or invoice/PO lines) if — and only if — the club actually charges VAT and files aangifte. First step is a **model clarification** with the treasurer: if membership fees are VAT-exempt, the auto-21% fills in `InvoiceForm`/`PurchaseOrderForm`/`BillableItemSection` produce misleading data and should be corrected (rate field / exempt flag) before any reporting.
- **Pain relieved**: either a missing aangifte input, or (worse) silently wrong VAT data poisoning every report that shows it.
- **Size**: small for a totals table; medium if `vat` becomes a rate/exempt-aware model (touches forms, seeders, bookkeeping generation).

### Candidate G — ANBI annual financial overview (small if clustered with A)

- **Today**: zero ANBI-specific code or data. The inputs exist: cost-center results (baten/lasten), member counts (`MemberOverview`), activities.
- **Missing**: an annually publishable financial overview (the ANBI requirement is that the vereniging publishes its financial overview publicly). Realistically this is candidate A's export framed/packaged for publication.
- **Pain relieved**: manual assembly of the published overview each year.
- **Size**: small if it reuses A; the judge should first confirm with the user that the club actually holds ANBI status (unknown from the codebase).

### Candidate H — Year-end reconciliation checklist (small; clusters with A/E)

- **Today**: the checks exist individually — statement integrity, statement chaining, per-transaction unmatched amounts, unresolved-transaction widget (1.7) — spread across four screens.
- **Missing**: a consolidated per-year "is this book year complete" report: all statements per account Valid + chained; no Open/Unresolved transactions; no unmatched amounts; (optionally) no bookkeeping records for *paid* documents lacking a bank-transaction link — turning the TODO-item-3 "ensure" into a verifiable, recurring check.
- **Pain relieved**: year-end confidence currently means manually auditing five different admin screens; this makes the completeness claim one glance.
- **Size**: small — read-only queries over existing flags, one Filament page; naturally part of E's closing flow.

---

## 3. Checked and already covered

Hypotheses from the charting session that the code refutes or already covers:

- **Trial balance (proefbalans)** — *refuted as a standalone candidate.* A strict trial balance needs debit/credit accounts (grootboek), which this project explicitly decided against (`.spec/questions/20260822_multiple-bank-accounts-bookkeeping.md` §5 "Not recommended"). Within the single-entry cost-center model, the per-cost-center per-year totals on `CostCenterResults` *are* the meaningful equivalent and already exist. Only propose if the user reopens the grootboek decision.
- **Budget vs actual reporting per cost center** — *already covered on screen*: `budget_amount` and `budget_difference` columns with totals on `CostCenterResults` (budget amounts added 2026-09-30). The genuine gaps are the missing baten/lasten split, VAT, and export — folded into candidate A, not proposed separately.
- **Bank reconciliation report** — *the mechanics already exist* (statement integrity + chain status + matched % + per-transaction unmatched + unresolved widget; see 1.7). What's missing is aggregation and per-account scoping — that is candidates D and H; a separate "reconciliation feature" would duplicate existing checks.
- **General ledger export for SnelStart/Exact/eBoekhouden** — *confirmed absent* (1.4) and proposed as candidate B; no partial implementation exists to build on (no CSV code, no package formats, no dependencies).
- **Map-excluded items** — description-based invoice matching with optional IBAN (open TODO.md item) and the SEPA-export TODOs are excluded by the map and are **not proposed**; note for context only: purchase orders (incl. member declarations) already flow into the SEPA export via `SepaBatchRepositoryDb::getPurchaseOrdersForExport()`. Member-portal-shaped reporting (members viewing their own invoices) is excluded by design.

## Sources

Codebase (primary):

- `app/Domain/Bookkeeping/` + `app/Infrastructure/Bookkeeping/` — `BookkeepingRecordRepository`/`BookkeepingRecordDbRepository` (all `createFor*`, `linkToBankTransaction`, `getResultsForYear`), `BankTransactionDbRepository` (`complete()`, `linkReversal()`, internal transfers), `BankAccountRepository`/`BankAccountDbRepository` (`getOpeningAmount`), `DetermineBankStatementChainStatus*`.
- `app/Models/` — `BookkeepingRecord`, `CostCenter`, `CostCenterBudget`, `BankAccount`, `BankAccountYearBalance`, `BankStatement`, `BankTransaction` (matched/unmatched accessors), `Invoice`, `SepaBatch`, `PurchaseOrder`.
- `app/Filament/Admin/Pages/CostCenterResults.php`; `app/Filament/Admin/Resources/{BookkeepingRecords,CostCenters,BankAccounts,BankStatements,BankTransactions,Invoices,PurchaseOrders,SepaBatches}/…`; `app/Filament/Admin/Widgets/Dashboard/{OpenInvoicesWidget,OpenPurchaseOrdersWidget,UnresolvedBankTransactionsWidget}.php`; `app/Filament/Admin/Actions/{ImportMt940Action,CreateCostCenterBudgetsAction}.php`.
- `database/migrations/` — `20260622` bookkeeping records, `20260623` cost center budgets, `20260930` budget_amount, `20260822_*` bank accounts/year balances/statements/transaction links, `20260705_*` transaction references.
- `lang/nl/labels.php` (incl. unused `expected_closing_balance` at line 363; `revenue` at 392; declaration labels 396-407).
- `composer.json` (no export/PDF packages), `routes/console.php` (hourly matching schedule).
- `.agents/docs/bookkeeping/CONTEXT.md`, `.agents/docs/GLOSSARY.md`, `docs/architecture.md`.

Analysis history:

- `.spec/questions/20260822_multiple-bank-accounts-bookkeeping.md` — Dutch legal framework (art. 2:10 BW, art. 52 AWR), rejection of the grootboek/double-entry rewrite, the unbuilt per-account balance overview.
- `.spec/questions/20260923_member-declarations-implementation.md` — declarations-as-purchase-orders state.
- `.spec/plans/20260623_bookkeeping-solution.md`, `20260808_bookkeeping-record-relations-view.md`, `20260818_monthly-saldo-validation-banking-transactions.md`, `20260822_multiple-bank-accounts-bookkeeping.md`, `20260930_cost-center-budgets-budget-amount.md`.
- `TODO.md` (items 3, 5, 6 closed — relations view, statement balances, multiple accounts).
