# Multiple bank accounts in the bookkeeping (Dutch-law compliant)

**Date**: 2026-08-22
**Question**: How do we fit multiple bank accounts (checking + savings) into the bookkeeping so we can import transactions for all of them, give each account a starting amount per year, and easily see if we missed a transaction — while adhering to Dutch law and staying easy to maintain?

---

## Summary

### Recommended: `BankAccount` entity + per-year opening balance + persist the MT940 statement data we currently throw away

Introduce a small `BankAccount` entity (one row per real bank account, keyed by IBAN), link every `BankingTransaction` to it, store an explicit **opening balance per account per book year**, and start persisting the MT940 **statement-level data** the parser already gives us but the import currently discards (statement account `:25:`, opening/closing balance `:60F:`/`:62F:`, statement number `:28C:`). Missing transactions then surface through three cheap checks: (1) per-statement balance check, (2) statement chain check, (3) year opening balance + running total vs. actual bank balance. This mirrors how Dutch bookkeeping packages model bank accounts (bankboek with an opensaldo per boekjaar) and directly supports the duties in art. 2:10 BW.

### Good alternative (minimal): only `bank_account_id` + opening-balance rows

Skip the statement entity and rely solely on the year opening balance + running total. Less to build and maintain, but you lose the strongest missing-transaction detection (statement chaining) and the bank-official audit anchor, which weakens both the operational goal and the art. 2:10 BW "administration must be verifiable" story.

### Not recommended: full double-entry grootboek

Modeling each bank account as a grootboekrekening with journaalposten (the classic Odoo/Twinfield/e-Boekhouden model) is the formally complete answer, but it is a rewrite of the current single-entry cost-center system. Disproportionate for a vereniging administration of this size.

---

## 1. Current state (why something must change)

Findings from the codebase (see also `TODO.md` items 5 and 6, which request exactly this):

| Aspect | Current state | Problem with multiple accounts |
|---|---|---|
| Bank account entity | **Does not exist.** The club's own IBAN only lives in `config/sepa.php` (`SEPA_CREDITOR_IBAN`) for SEPA export. | Nothing to hang transactions, opening balances, or reports on. |
| `banking_transactions.banking_account_number` | Holds the **counterparty** IBAN (`:86:` field), not our own account (`app/Infrastructure/BankTransactions/BankTransactionImportServiceImpl.php:49`). | We cannot tell which of our accounts a transaction happened on. |
| MT940 statement account (`:25:`) | Read but **only mixed into the import hash** (`BankTransactionImportServiceImpl.php:60-71`), never persisted. | Account info is lost after import. |
| MT940 statement balances (`:60F:`/`:62F:`), statement number (`:28C:`) | **Ignored entirely.** The parser exposes them (`vendor/kingsquare/php-mt940/src/Banking/Statement.php`: `getStartPrice()`, `getEndPrice()`, `getNumber()`, `getStartTimestamp()`, `getEndTimestamp()`, `getCurrency()`). | No way to verify completeness of the import. |
| Aggregations | `RunningTotalSummery` (`app/Filament/Admin/Resources/BankingTransactions/Tables/RunningTotalSummery.php`), month grouping + `Sum` summarizer, `BankingTransactionStats` widget all sum across **all** transactions. | Running totals and month sums become meaningless when two accounts are mixed. |
| Matching | `TransactionMatchingServiceImpl` and `BankTransactionDbRepository` matching queries are account-agnostic. | Cross-account false positives become possible. |
| Manual create | `CreateBankingTransaction.php:24-29` computes the import hash without a statement account. | Hash semantics diverge from MT940 imports. |
| Bookkeeping | `bookkeeping_records` has no account dimension; records link to a `BankingTransaction` via nullable FK. | Per-account bookkeeping views impossible; internal transfers between own accounts would be double-counted as revenue/expense. |

---

## 2. Dutch legal framework (what the design must satisfy)

WSV Almere Centraal is a **vereniging**, a legal entity (rechtspersoon), so **art. 2:10 Burgerlijk Wetboek Boek 2** applies to its board (confirmed to cover verenigingen explicitly by Dirkzwager, "De administratieplicht van de bestuurder"). Full article text verified at wetboek.org/bw/2/10:

| Rule | Content | Implication for this design |
|---|---|---|
| **Art. 2:10 lid 1 BW** | The board must keep an administration of the financial position and all activities, in such a way that **rights and obligations can be known at all times** ("dat te allen tijde de rechten en verplichtingen van de rechtspersoon kunnen worden gekend"). | **Every** bank account of the vereniging must appear in the administration, completely. Balance reconciliation (opensaldo + mutations = saldo) is the standard way to prove completeness — the design must make gaps visible, which is exactly the requested "see if we missed a transaction". |
| **Art. 2:10 lid 2 BW** | Within **6 months** after the end of the book year the board must produce a **balans** and **staat van baten en lasten** on paper. | Bank balances are balans items (liquiditeiten). Per-account year-start/year-end balances must be producible per book year — hence the per-year opening balance. |
| **Art. 2:10 lid 3 BW** | Books, documents and data carriers must be kept for **7 years**. | Keep the original MT940 files (already stored under `storage/app/private/mt940-imports` by the import action, `ListBankingTransactions.php:32-41`) **and** the imported data. Never purge banking/bookkeeping data younger than 7 years. |
| **Art. 2:10 lid 4 BW** | Data may be transferred to another data carrier (e.g. imported into a database) if the representation is **correct and complete** and stays available/readable during the retention period. | Importing MT940 into the DB is lawful, provided statement-level data (number, balances) is preserved — another argument for persisting statement data instead of discarding it. |
| **Art. 52 AWR** | Fiscal retention duty of **7 years** (term starts after the end of the book year) for the basic administration: grootboek, debiteuren/crediteuren, in- en verkoopadministratie, bankafschriften. Applies to a vereniging to the extent it has tax obligations (BTW on e.g. bar/verhuur, or VPB if it runs an enterprise). | Same practical consequence: retain everything 7 years; the MT940 import files are the digital bankafschriften. |
| **Art. 52a AWR** | **10 years** retention for data concerning immovable property. | Only relevant if the club owns the clubhouse/grounds; in that case keep purchase/investment records (already partly covered by the Inventory entity) for 10 years. |
| **BTW** | VAT is tracked at **invoice level**, not per bank account. | No change needed: `bookkeeping_records.amount_vat` already splits VAT per record; the bank amount is gross. |

**Conclusion:** nothing in Dutch law *requires* a specific data model, but art. 2:10 lid 1 BW effectively requires (a) all bank accounts in the administration, (b) a way to prove completeness (balance reconciliation), and (c) 7-year retention of the source statements. The recommended design satisfies all three with minimal machinery.

---

## 3. Recommended solution (Solution 1)

The pattern below is the hybrid used by Moneybird and Firefly III, adapted to this codebase's single-entry cost-center model, and it matches the classic Dutch "bankboek met opensaldo per boekjaar" concept.

### 3.1 Data model (4 changes)

1. **New entity `BankAccount`** (`bank_accounts` table)
   - `id`, `name` ("Betaalrekening", "Spaarrekening"), `iban` (unique, normalized — uppercase, no spaces), `bic` (nullable), `active` (boolean), timestamps.
   - Managed by the `FinancialAdministration` role via a small Filament resource in the Bookkeeping cluster.
   - Seed from the existing `SEPA_CREDITOR_IBAN` config value (the current checking account) + the savings account IBAN the treasurer provides.
   - **Accounts are created deliberately in the UI, never auto-created at import.** An MT940 file with an unknown `:25:` IBAN should be *rejected with a clear error* ("onbekende bankrekening, voeg deze eerst toe"). This keeps the administration controlled and auditable — a bank account appearing silently via import is exactly the kind of gap art. 2:10 lid 1 BW is meant to prevent.

2. **New table `bank_account_year_balances`** (the per-year starting amount the user asked for)
   - `id`, `bank_account_id` (FK), `year` (year), `opening_amount` decimal(10,3), timestamps, `unique(bank_account_id, year)`.
   - One row per account per book year, entered by the treasurer (Filament RelationManager on the BankAccount resource, same pattern as `CostCenterBudgetsRelationManager` on CostCenter).
   - Explicit rows (instead of one-time opening balance + carry-forward) because: (a) the user explicitly wants a starting amount *each year*, (b) it matches the Dutch opensaldo-per-boekjaar convention, (c) it gives a yearly checkpoint: if year N opening ≠ year N−1 computed closing, something was missed.

3. **New entity `BankStatement`** (`bank_statements` table)
   - `id`, `bank_account_id` (FK), `statement_number` (MT940 `:28C:`), `start_date`, `end_date`, `opening_balance` (`:60F:`), `closing_balance` (`:62F:`), `currency`, `file_path` (link to the retained original MT940 file — bewaarplicht), timestamps, `unique(bank_account_id, statement_number)` (idempotent re-import).
   - All of this is already parsed by `kingsquare/php-mt940` (`Statement::getNumber()`, `getStartPrice()`, `getEndPrice()`, `getStartTimestamp()`, `getEndTimestamp()`, `getCurrency()`) — we only have to stop discarding it.

4. **Extend `banking_transactions`**
   - `bank_account_id` (FK → bank_accounts, nullable at first for backfill, indexed) — resolved at import from the statement's `:25:` IBAN.
   - `bank_statement_id` (FK → bank_statements, nullable — manual entries have none).
   - Keep `banking_account_number` as the **counterparty** IBAN (consider renaming to `counterparty_account_number` in a later cleanup to remove the ambiguity; the glossary should pin this down).

### 3.2 Import flow changes

`BankTransactionImportServiceImpl::importFromFile()` (app/Infrastructure/BankTransactions/BankTransactionImportServiceImpl.php) becomes, per parsed statement:

1. Resolve `BankAccount` by `$statement->getAccount()`; **abort the file with an error notification** if unknown.
2. Upsert the `BankStatement` row (idempotent by account + statement number; re-importing the same file skips everything via existing hashes).
3. **Statement integrity check**: `opening_balance + Σ transaction amounts == closing_balance`. On mismatch, still import but flag the statement (or reject the file) — a mismatch means the file itself is corrupt/incomplete.
4. **Chain check**: new statement's `opening_balance` must equal the previous statement's `closing_balance` for that account. A break means **a statement (and its transactions) was never imported** — the most reliable "missed transactions" detector there is.
5. Create transactions as today, now with `bank_account_id` and `bank_statement_id` set. The import hash already contains the statement account, so cross-account deduplication stays correct; the manual-create hash (`CreateBankingTransaction.php`) should be aligned to include the account too.

### 3.3 Missing-transaction detection (the user's core requirement)

Three layers, cheapest first:

| Check | Formula | Catches |
|---|---|---|
| **Year balance check** | `opening_amount(year) + Σ transactions in year` = expected current balance; show this next to the actual bank balance the treasurer enters (or just show the expected balance and let them compare with the bank app) | Any missed/duplicate transaction in the year — this is the direct answer to "easily see if we missed a transaction" |
| **Statement chain check** | statement N opening == statement N−1 closing | A whole missing statement/file |
| **Statement internal check** | opening + Σ lines == closing | Corrupt or partial file |

The year balance check is what the existing `RunningTotalSummery`/month-grouping work (`.spec/plans/20260818_monthly-saldo-validation-banking-transactions.md`) was building towards — it just needs to be **scoped per account** (a required account filter on the transactions table, and running totals computed per account).

### 3.4 Internal transfers between own accounts (the one genuinely new bookkeeping concern)

With two accounts, a transfer checking → savings produces **two** transactions (−X on checking, +X on savings). These must **not** hit cost-center results as expense/revenue. Recommended handling, reusing an existing mechanism:

- Use the same link pattern already built for SEPA reversals (`reversed_by_transaction_id` / `linkReversal` in `BankTransactionDbRepository`): a generic **transaction link** with a link type (`reversal` | `internal_transfer`), or a dedicated `linked_to_transaction_id` for transfers.
- Completing a transfer pair books nothing to cost centers (no BookkeepingRecord), so cost-center results stay correct; both transactions get status Completed, resolve status a new `Transferred`-like value or `Resolved` via the link.
- Matching should **never** propose invoices/POs for a transaction whose counterparty IBAN is one of our own `bank_accounts.iban` values — instead suggest the transfer link. This is a simple, robust rule: counterparty IBAN ∈ own accounts ⇒ internal transfer.

### 3.5 Bookkeeping integration

- `BookkeepingRecord` **does not need an account column for cost-center results** (revenue/expense classification is account-independent, and VAT stays at invoice level). The account is always reachable via `banking_transaction_id` when a report needs it.
- If per-account bookkeeping totals are wanted later (TODO item 6's "bookkeeping records for both accounts"), add a nullable `bank_account_id` to `bookkeeping_records`, auto-filled from the linked transaction — but only then; deriving it via the join keeps today's model simpler and avoids a consistency risk.
- `CostCenterResults` page is unaffected (it aggregates cost-center flows, not bank balances). A new small **per-account balance overview** (account, opensaldo, Σ bij, Σ af, expected eindsaldo, last statement date, chain status) is the natural new page for the treasurer.

### 3.6 Existing code that must become account-aware (checklist)

- `BankTransactionImportServiceImpl` — resolve account, persist statement (3.2).
- `CreateBankTransaction` DTO + `BankTransactionDbRepository::create()` — carry `bank_account_id`/`bank_statement_id`.
- `CreateBankingTransaction` Filament page — account selector + aligned hash.
- `BankingTransactionsTable` — account column + **default account filter**; `RunningTotalSummery` and the month `Sum` must scope per account (today they sum everything).
- `BankingTransactionStats` widget — scope per account (or show per-account breakdown).
- `TransactionMatchingServiceImpl` / `BankTransactionDbRepository` matching + `getUnresolvedIds` — scope candidate matching per account; own-IBAN rule for transfers (3.4).
- `GenerateMt940Command` (dev fixture) — emit `:25:` per account so multi-account imports can be tested.
- `.agents/docs/banking/CONTEXT.md`, `.agents/docs/bookkeeping/CONTEXT.md`, `.agents/docs/GLOSSARY.md` — add BankAccount/BankStatement terms, fix the `banking_account_number` ambiguity.
- Permissions: `bank_accounts` CRUD for `FinancialAdministration` (existing pattern: `ResourcePermission` enum + `ResourcePolicy` + `RolePermissionSeeder`).

### 3.7 Migration/backfill

All existing transactions belong to the current checking account: create the `BankAccount` rows, backfill `bank_account_id` from the `SEPA_CREDITOR_IBAN` value, then make the column NOT NULL. Enter the opening balances for the current book year from the bank app. No historical data is lost; hashes stay valid.

---

## 4. Alternative: minimal version (Solution 2)

If maintenance surface must be absolutely minimal:

- `bank_accounts` table + `banking_transactions.bank_account_id` (same as above).
- `bank_account_year_balances` (same as above).
- **No** `bank_statements` table; statement `:25:`/`:60F:`/`:62F:`/`:28C:` stay discarded.
- Missing-transaction detection only via the year balance check (opening + running total vs. actual bank balance), done manually by the treasurer.

**Trade-off:** one table and one integrity feature less, but (a) a missing *statement* is only discoverable if the treasurer notices the balance drift, (b) no bank-official anchor (statement number/balances) is retained, which makes the "correct and complete" position under art. 2:10 lid 4 BW weaker, and (c) if statements are ever wanted later, historical ones can't be reconstructed. Given that the parser already provides all statement data for free, the statement table is cheap insurance and part of the recommendation.

## 5. Not recommended: full grootboek / double-entry (Solution 3)

The classic Dutch model (each bank account = grootboekrekening in a rekeningschema, one bankboek per account, journaalposten, vraagposten/tussenrekening for unmatched lines, opensaldo via openingsjournaalpost, years locked after closing) is what e-Boekhouden, Twinfield, Exact Online, SnelStart do. It is the "complete" answer and scales to any future reporting need.

Why not here: this application is deliberately a **single-entry cost-center system** — BookkeepingRecords are projections of invoices/POs/bank flows, not a ledger. Introducing double-entry means a chart of accounts, journal entries, trial balance, and a rewrite of matching, bookkeeping generation, and all reports. For a vereniging with two bank accounts the balance-check approach of Solution 1 gives the same practical assurance (a sluitende bankadministratie) at a fraction of the complexity. If the club ever needs a real jaarrekening-grade grootboek, that is a case for dedicated software, not for rebuilding it inside this admin.

## 6. Comparison

| Criterion | Solution 1 (recommended) | Solution 2 (minimal) | Solution 3 (grootboek) |
|---|---|---|---|
| Multiple accounts importable | ✅ | ✅ | ✅ |
| Per-year starting amount | ✅ | ✅ | ✅ (opensaldo per boekjaar) |
| Detect missed transaction | ✅✅✅ (3 automatic checks) | ✅ (manual balance compare) | ✅ (afletteren + reconciliation) |
| Art. 2:10 BW compliance | ✅ full (incl. lid 4 completeness) | ⚠️ weaker audit anchor | ✅ full |
| Effort | Medium (3 new tables, import rework) | Low (2 new tables) | Very high (system rewrite) |
| Ongoing maintenance | Low — accounts rarely change; checks are automatic | Lowest | High |

## 7. Suggested phasing for Solution 1

1. **Phase 1 — accounts & opening balances**: `bank_accounts`, `bank_account_year_balances`, `banking_transactions.bank_account_id` (+ backfill), BankAccount resource, account filter on the transactions table, per-account running totals. Already delivers the user's core ask.
2. **Phase 2 — statements & integrity**: `bank_statements`, import rework (3.2), chain + internal balance checks, per-account balance overview page.
3. **Phase 3 — transfers**: own-IBAN detection, transfer linking, exclusion from cost-center results.

## 8. Open questions (for product/treasurer)

1. What is the savings account IBAN (and any future accounts), and what are the opening balances for the current book year?
2. Should an MT940 file with an unknown `:25:` IBAN be rejected outright (recommended) or parked for review?
3. Are there historical statements that still need importing (affects how far back the chain check can verify)?
4. Does the club own immovable property (clubhouse/grounds)? If yes, the 10-year retention of art. 52a AWR applies to those records (inventory items already support this direction).

---

## Sources

**Dutch law & practice**
- Art. 2:10 BW (full text, verified): https://wetboek.org/bw/2/10
- Art. 2:10 BW applies to verenigingen (administratieplicht bestuurder): https://www.dirkzwager.nl/kennis/artikelen/de-administratieplicht-van-de-bestuurder
- Bewaarplicht vereniging (7 jaar, soms 10): https://www.verenigingen.nl/uitleg/bewaarplicht/
- Art. 52 AWR, 7 jaar vanaf einde boekjaar: https://mijnbv.nu/boekhouding/kennis/administratie-bewaarplicht-ondernemer-7-jaar
- Belastingdienst, administratie bewaren (7/10 jaar, basisgegevens): https://www.belastingdienst.nl/wps/wcm/connect/bldcontentnl/belastingdienst/zakelijk/btw/administratie_bijhouden/administratie_bewaren/administratie_bewaren
- Boekhoudplicht juridische grenzen: https://mkbtr.nl/kennislab/de-boekhoudplicht-waar-liggen-de-juridische-grenzen/

**Multi-account bookkeeping models**
- Firefly III account types & reconciliation: https://docs.firefly-iii.org/references/firefly-iii/account-types/ , https://docs.firefly-iii.org/how-to/firefly-iii/finances/reconcile/
- GnuCash manual §5.8 reconciliation (difference must be 0) & opening balances: https://www.gnucash.org/docs/v5/C/gnucash-manual/acct-reconcile.html , https://www.gnucash.org/docs/v5/C/gnucash-guide/chapter_txns.html
- Odoo bank journals (one journal per bank account) & reconciliation: https://www.odoo.com/documentation/18.0/applications/finance/accounting/bank.html , https://www.odoo.com/documentation/18.0/applications/finance/accounting/bank/reconciliation.html
- Moneybird OpenAPI (financial_account / financial_statement with official_balance / mutation states): https://github.com/moneybird/openapi
- e-Boekhouden grootboekrekeningen & bankkoppelingen: https://www.e-boekhouden.nl/functies/grootboekrekeningen , https://www.e-boekhouden.nl/koppelingen/banken
- Grootboek/dagboek/bankboek concepten: https://nl.wikipedia.org/wiki/Grootboek , https://nl.wikipedia.org/wiki/Dagboek_(financieel)

**Codebase**
- MT940 import: `app/Infrastructure/BankTransactions/BankTransactionImportServiceImpl.php` (L23-85), `app/Filament/Admin/Resources/BankingTransactions/Pages/ListBankingTransactions.php` (L28-57)
- Parser capabilities: `vendor/kingsquare/php-mt940/src/Banking/Statement.php` (`getAccount`, `getStartPrice`, `getEndPrice`, `getNumber`, `getCurrency`)
- Current schema: `database/migrations/2026_07_05_074926_create_banking_transactions_table.php`, `2026_06_22_054611_create_bookkeeping_records_table.php`, `2026_07_05_075109_*` (references pivot + banking_transaction_id)
- Account-blind aggregations: `app/Filament/Admin/Resources/BankingTransactions/Tables/RunningTotalSummery.php`, `.spec/plans/20260818_monthly-saldo-validation-banking-transactions.md`
- Reversal-link pattern to reuse for transfers: `app/Infrastructure/BankTransactions/BankTransactionDbRepository.php` (`linkReversal`, L245-247), `app/Models/BankingTransaction.php` (L56-88)
- Feature request: `TODO.md` items 5 & 6
