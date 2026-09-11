# Plan: Multiple Bank Accounts Bookkeeping (Dutch-law compliant)

**Date**: 2026-08-22
**Source**: `.spec/questions/20260822_multiple-bank-accounts-bookkeeping.md`
**Approach**: Implement **Solution 1** from the questions file — a `BankAccount` entity + per-year opening balance + a persisted `BankStatement` entity (the MT940 statement metadata we currently throw away). Keep the existing single-entry cost-center bookkeeping model; do **not** introduce a general ledger or double-entry journal.

---

## 1. Product & domain decisions

- Model every association-owned account as a deliberate `BankAccount`, keyed by a **normalized** IBAN (uppercase, no spaces). Accounts are created **in the UI only** — never auto-created at import.
- An MT940 file whose `:25:` account IBAN is unknown is **rejected atomically** with a clear Dutch error (`Onbekende bankrekening, voeg deze eerst toe`). No partial import may remain.
- Persist the statement metadata the parser already exposes (`:28C:` number, `:60F:`/`:62F:` balances, start/end timestamps, currency) into `bank_statements`.
- Preserve `banking_transactions.banking_account_number` as the **counterparty** IBAN (do not rename it in this feature — only document the ambiguity). Add a separate owning `bank_account_id`.
- Missing-transaction detection is three-fold: (1) per-statement internal balance check, (2) statement chain check, (3) year opening balance + running total. Integrity/chain problems are **persisted and shown**, never silently discarded.
- Own-account counterparties (IBAN ∈ owned accounts) are **internal transfers** — never matched to invoices/POs, never produce `BookkeepingRecord` rows.
- Keep `BookkeepingRecord` without an account column; the account is reachable via `banking_transaction_id`.
- Retain original MT940 files (already stored under `storage/app/private/mt940-imports`) for ≥7 years; no purge.

Open values (from the treasurer, not hardcoded): savings IBAN, current-year opening amounts, historical statement range.

---

## 2. Existing architecture to preserve (verified)

- Import lives in `app/Infrastructure/BankTransactions/BankTransactionImportServiceImpl.php` (`:23-57`): parses via `Kingsquare\Parser\Banking\Mt940` and `Kingsquare\Banking\Statement`; the statement account is only mixed into the hash (`:60-71`); transactions are created through `CreateBankTransaction` + `BankTransactionRepository`.
- Parser API (verified in `vendor/kingsquare/php-mt940/src/Banking/Statement.php`): `getAccount()`, `getStartPrice()`, `getEndPrice()`, `getNumber()`, `getCurrency()`, `getStartTimestamp($format)`, `getEndTimestamp($format)`, `getTransactions()`.
- Domain contracts use `#[Autowire]` (resolved by name to `…Impl`): `app/Domain/BankTransactions/BankTransactionRepository.php`, `BankTransactionImportService.php`, `TransactionMatchingService.php`. A PHPStan rule (`dev/phpstan/Rules/DomainDependencyRule.php`) forbids domain → infrastructure/`App\Models` dependencies — new domain types must be value objects/IDs, not Eloquent models.
- `BankingTransaction` (`app/Models/BankingTransaction.php`) uses `#[Guarded(['id','created_at','updated_at'])]`, a `casts()` override, and owns invoice/PO/bookkeeping/reversal relations.
- Filament financial resources use split `Resource`/`Schemas`/`Tables`/`Pages`/`RelationManagers`/`Widgets` classes under `app/Filament/Admin/Resources/…`, all inside `BookkeepingCluster` (`app/Filament/Admin/Clusters/Bookkeeping/BookkeepingCluster.php`, restricted to `RoleName::FinancialAdministration`). Auto-discovery via `app/Providers/Filament/AdminPanelProvider.php:45-48` — no manual registration needed.
- Authorization: `app/Domain/Authorization/ResourcePermission.php` (TitleCase enum), `app/Policies/ResourcePolicy.php` (abstract, `permissionPrefix()`), concrete policies, `database/seeders/RolePermissionSeeder.php:70-97` (`allPermissionsFor($resource)`).
- Month grouping + `Sum` + `RunningTotalSummery` already exist (`BankingTransactionsTable.php`, `RunningTotalSummery.php`, `MonthGroup.php`) and currently sum **all** accounts — must become account-scoped.
- Labels are Dutch flat keys in `lang/nl/labels.php`.

---

## 3. Persistence & migrations

Create **forward, reversible** migrations (do not edit already-run migrations). Use `decimal(10,3)` (project monetary precision), `$table->year('year')` for book years, and `restrictOnDelete()` FKs so retained financial history can never cascade-delete.

### 3.1 `bank_accounts`

`database/migrations/YYYY_MM_DD_HHMMSS_create_bank_accounts_table.php`

```php
Schema::create('bank_accounts', static function (Blueprint $table): void {
    $table->id();
    $table->string('name');
    $table->string('iban')->unique();          // normalized: uppercase, no spaces
    $table->string('bic')->nullable();
    $table->boolean('active')->default(true);
    $table->timestamps();
});
```

### 3.2 `bank_account_year_balances`

`database/migrations/YYYY_MM_DD_HHMMSS_create_bank_account_year_balances_table.php`

```php
Schema::create('bank_account_year_balances', static function (Blueprint $table): void {
    $table->id();
    $table->foreignId('bank_account_id')->constrained('bank_accounts')->restrictOnDelete();
    $table->year('year');
    $table->decimal('opening_amount', 10, 3);
    $table->timestamps();
    $table->unique(['bank_account_id', 'year']);
    $table->index(['bank_account_id', 'year']);
});
```

### 3.3 `bank_statements`

`database/migrations/YYYY_MM_DD_HHMMSS_create_bank_statements_table.php`

```php
Schema::create('bank_statements', static function (Blueprint $table): void {
    $table->id();
    $table->foreignId('bank_account_id')->constrained('bank_accounts')->restrictOnDelete();
    $table->string('statement_number');            // MT940 :28C:
    $table->date('start_date');
    $table->date('end_date');
    $table->decimal('opening_balance', 10, 3);     // :60F:
    $table->decimal('closing_balance', 10, 3);     // :62F:
    $table->string('currency', 3)->default('EUR');
    $table->string('file_path');                   // retained original MT940 (bewaarplicht)
    $table->string('integrity_status')->default('valid');   // valid | mismatch
    $table->string('chain_status')->default('baseline');    // baseline | ok | broken
    $table->decimal('balance_difference', 10, 3)->default(0);
    $table->timestamps();
    $table->unique(['bank_account_id', 'statement_number']);
    $table->index(['bank_account_id', 'start_date']);
});
```

### 3.4 Extend `banking_transactions`

`database/migrations/YYYY_MM_DD_HHMMSS_add_bank_account_and_statement_to_banking_transactions.php`

```php
Schema::table('banking_transactions', static function (Blueprint $table): void {
    $table->foreignId('bank_account_id')
        ->nullable()                       // non-null after backfill
        ->constrained('bank_accounts')
        ->restrictOnDelete();
    $table->foreignId('bank_statement_id')
        ->nullable()                       // manual entries have none
        ->constrained('bank_statements')
        ->restrictOnDelete();
    $table->index(['bank_account_id', 'date']);
});
```
---

## 4. Models & factories

All in `app/Models/`, guarded IDs/timestamps, `casts()`, `HasFactory`. Add factories in `database/factories/` mirroring `CostCenterBudgetFactory` / `BankingTransactionFactory`.

### `app/Models/BankAccount.php`

```php
#[Guarded(['id', 'created_at', 'updated_at'])]
final class BankAccount extends Model
{
    use HasFactory;

    /** @return HasMany<BankingTransaction, $this> */
    public function transactions(): HasMany;
    /** @return HasMany<BankStatement, $this> */
    public function statements(): HasMany;
    /** @return HasMany<BankAccountYearBalance, $this> */
    public function yearBalances(): HasMany;

    // casts(): 'active' => 'boolean'
}
```

IBAN normalization lives at the model/form boundary (an `setIbanAttribute` mutator uppercasing and stripping spaces, plus a Filament form rule that normalizes before `unique` validation).

### `app/Models/BankAccountYearBalance.php`

- `#[Fillable(['bank_account_id','year','opening_amount'])]`, `BelongsTo` `bankAccount()`, casts `opening_amount => 'decimal:3'`, `year => 'integer'`.

### `app/Models/BankStatement.php`

- `#[Guarded(['id','created_at','updated_at'])]`, `BelongsTo` `bankAccount()`, `HasMany` `transactions()`; casts: `start_date`/`end_date` → `date`, balances → `decimal:3`, `integrity_status`/`chain_status` → string-backed enums (see §7).

### `app/Models/BankingTransaction.php` (extend)

- Add `/** @return BelongsTo<BankAccount, $this> */ public function bankAccount(): BelongsTo` and `/** @return BelongsTo<BankStatement, $this> */ public function bankStatement(): BelongsTo`.
- Add `'bank_account_id' => 'integer'`, `'bank_statement_id' => 'integer'` casts (consistent with the existing `reversed_by_transaction_id` integer cast).

### Factories

- `database/factories/BankAccountFactory.php`: `name`, normalized `iban` (`strtoupper(fake()->iban('NL'))`), nullable `bic`, `active` default true. States: `checking()`, `savings()`, `inactive()`.
- `database/factories/BankAccountYearBalanceFactory.php`: `bank_account_id => BankAccount::factory()`, `year => now()->year`, `opening_amount`.
- `database/factories/BankStatementFactory.php`: `bank_account_id`, `statement_number`, `start_date`, `end_date`, `opening_balance`, `closing_balance`, `currency`, `file_path`, integrity/chain statuses. States: `mismatch()`, `brokenChain()`.
- Extend `BankingTransactionFactory` with `forBankAccount(BankAccount $account)` and `forStatement(BankStatement $statement)` states.

---

## 5. Domain contracts & repositories

New domain subdirectories follow the existing convention (`app/Domain/BankAccounts`, `app/Domain/BankStatements`), with `#[Autowire]` interfaces implemented by `app/Infrastructure/…` classes. Domain interfaces must not reference `App\Models` — return value objects/IDs.

### `app/Domain/BankAccounts/`

- **`BankAccountId`** — a `NumericId` subclass, copying `app/Domain/BankTransactions/BankTransactionId.php`.
- **`BankAccountRepository`** (`#[Autowire]` interface), implemented by `app/Infrastructure/BankAccounts/BankAccountDbRepository.php`:
  - `findByIban(string $iban): ?BankAccountId` — resolve a normalized IBAN to an account (null = unknown).
  - `getOwnIbans(): array` — `list<string>` of normalized IBANs of active accounts (for internal-transfer detection).
  - `getOpeningAmount(BankAccountId $id, int $year): ?float` — `null` when no opening-balance row exists (drives the "incomplete" state).
- **`BankAccountBalanceOverview`** — a `final readonly` value object: `bankAccountId`, `name`, `iban`, `openingAmount`, `inflow`, `outflow`, `expectedClosing`, `lastStatementDate`, `statementIntegrityStatus`, `statementChainStatus`. Used by the balance-overview page and tests.
- **`BankAccountBalanceRepository`** (`#[Autowire]` interface), implemented by `app/Infrastructure/BankAccounts/BankAccountBalanceDbRepository.php`:
  - `getOverviewForYear(int $year): array` — `list<BankAccountBalanceOverview>`, computed with indexed account/date/year queries (no N+1).

### `app/Domain/BankStatements/`

- **`BankStatementId`** — a `NumericId` subclass.
- **`CreateBankStatement`** — `final readonly` DTO: `bankAccountId` (int), `statementNumber` (string), `startDate` (string), `endDate` (string), `openingBalance` (float), `closingBalance` (float), `currency` (string), `filePath` (string).
- **`BankStatementRepository`** (`#[Autowire]` interface), implemented by `app/Infrastructure/BankStatements/BankStatementDbRepository.php`:
  - `upsert(CreateBankStatement $dto): BankStatementId` — idempotent by `(bank_account_id, statement_number)`.
  - `findPrevious(BankAccountId $accountId): ?PreviousStatement` — returns a small value object (`?float closingBalance`, `?string statementNumber`); `null` = first known statement ⇒ `chain_status = baseline`.
  - `updateIntegrity(BankStatementId $id, string $integrityStatus, float $difference): void`.
  - `updateChain(BankStatementId $id, string $chainStatus): void`.

---

## 6. Import, DTO & matching rework

### 6.1 `CreateBankTransaction` DTO

Extend `app/Domain/BankTransactions/CreateBankTransaction.php`:

```php
public function __construct(
    public string $date,
    public float $amount,
    public string $description,
    public string $bankingAccountNumber,  // counterparty IBAN (meaning unchanged)
    public int $bankAccountId,            // owning account (new)
    public ?int $bankStatementId,         // statement (new; null for manual)
    public string $importHash,
) {}
```

Update `BankTransactionDbRepository::create()` (`app/Infrastructure/BankTransactions/BankTransactionDbRepository.php:29-40`) to persist `bank_account_id` and `bank_statement_id`. Update all callers (import service, manual-create path, tests/expectations).

### 6.2 `BankTransactionImportServiceImpl`

Update the constructor to inject `BankTransactionRepository`, `BankAccountRepository`, `BankStatementRepository`. Rewrite `importFromFile()`:

1. Parse all statements. **Pre-validate**: resolve every `Statement::getAccount()` to a `BankAccountId`; if any is unknown, throw a domain/import exception (Dutch unknown-account message) **before writing anything** (wrap the whole import in a DB transaction so a late failure rolls back).
2. Per statement (in date order):
   - `upsert` the `BankStatement` (idempotent by account+number), retaining `filePath`.
   - **Internal check**: `opening_balance + Σ transaction.getRelativePrice() == closing_balance`; on mismatch set `integrity_status = mismatch` and store `balance_difference` (import the lines anyway).
   - **Chain check**: previous statement `closing_balance` vs. current `opening_balance`; break ⇒ `chain_status = broken`, first statement ⇒ `baseline`, match ⇒ `ok`.
   - Create each transaction with `bankAccountId` + `bankStatementId`. Keep the existing hash (already includes statement account); skip duplicates via `existsByHash`.
3. Return `['imported' => int, 'skipped' => int, 'integrity_warnings' => int]` (additive key — the Filament page reads `imported`/`skipped` today and stays compatible).

### 6.3 Matching & repository account-awareness

- `app/Domain/BankTransactions/MatchCriteria.php`: add `?int $bankAccountId`.
- `BankTransactionDbRepository::getMatchCriteriaForIds()` (`:170-188`): populate `bankAccountId`.
- `TransactionMatchingServiceImpl::findMatch()` (`:20-33`): **first** check `BankAccountRepository::getOwnIbans()` against the counterparty IBAN; if own ⇒ return an internal-transfer result (§7), never propose invoice/PO matches.
- `BankTransactionDbRepository::findReversalMatch()` (`:207-226`): add `where('bank_account_id', $criteria->bankAccountId)` so reversals never match across accounts.
- `getUnresolvedIds()` stays account-agnostic (the job processes all), but matching criteria become account-scoped via `getMatchCriteriaForIds`.

### 6.4 Manual create hash

`app/Filament/Admin/Resources/BankingTransactions/Pages/CreateBankingTransaction.php:22-32` — include the selected `bank_account_id` (and IBAN) in the hash input so manual entries are consistent with MT940 dedup semantics.

---

## 7. Internal transfers

Extend the reversal-link pattern (`BankTransactionDbRepository::linkReversal()` `:228-249`; `BankingTransaction` relations `:55-88`):

- Add a typed link on `banking_transactions` (or a minimal `banking_transaction_links` table) with a `link_type` of `internal_transfer` (the existing reversal link stays `reversal`). Prefer a small pivot/columns pair (`linked_to_transaction_id` + `link_type`) if the current one-to-one reversal columns cannot represent a symmetric pair.
- A transfer pair books **no** `BookkeepingRecord`; both transactions get status `Completed` and resolve status `Resolved` via the link. Partial/unmatched transfers stay visible.
- Detection rule: counterparty IBAN ∈ `BankAccountRepository::getOwnIbans()` ⇒ internal transfer; matching returns a transfer-link result instead of invoice/PO.
- Guard against self-links, duplicate links, cross-account links, and non-idempotent re-linking.

**Scope note**: this is Phase 3 of the source document. If deferred, the account-scoping + statements + balances still fully deliver the core ask. Implement at minimum the *detection suppression* (own-IBAN ⇒ no invoice/PO proposals) even if full transfer linking is deferred.

## 8. Filament resources, pages & interface

All new classes under auto-discovered namespaces (`App\Filament\Admin\Resources\BankAccounts`, `App\Filament\Admin\Resources\BankStatements`, `App\Filament\Admin\Pages`). Use Dutch keys in `lang/nl/labels.php` and `BookkeepingCluster`. Reuse existing helper patterns (`IsOpen`, `RunningTotalSummery`, badge columns).

### 8.1 `BankAccount` resource (`app/Filament/Admin/Resources/BankAccounts/`)

Mirror `CostCenterResource` (`app/Filament/Admin/Resources/CostCenters/CostCenterResource.php`) + `BankingTransactionResource` conventions.

- `BankAccountResource.php` — model `BankAccount`, `BookkeepingCluster`, `NavigationGroup::Bookkeeping`, icon (`Heroicon::BuildingLibrary` or similar), `getLabel()`/`getPluralLabel()`, `navigationSort` after banking transactions, pages `index`/`create`/`edit`/`view`.
- `Schemas/BankAccountForm.php` — `name`, `iban` (normalize uppercase + strip spaces before `unique` validation), nullable `bic`, `active` toggle.
- `Tables/BankAccountsTable.php` — `name`, `iban`, `bic`, `active` badge, statements count, last statement date, current-year expected balance (from `BankAccountBalanceRepository`).
- `Pages/ListBankAccounts.php` (`CreateAction`), `CreateBankAccount.php`, `EditBankAccount.php` (`DeleteAction`), `ViewBankAccount.php`.
- `RelationManagers/BankAccountYearBalancesRelationManager.php` — relationship `yearBalances`; create/edit/delete an explicit opening amount per year (reuse the `CostCenterBudgetForm` shape: year + `starting_amount`), unique `(bank_account_id, year)` validation, `BulkActionGroup`.
- `RelationManagers/BankAccountStatementsRelationManager.php` — relationship `statements`; read-only list of statement history (number, dates, opening/closing, integrity/chain badges), row links to `BankStatementResource::getUrl('view', ...)`.

### 8.2 `BankStatement` resource (`app/Filament/Admin/Resources/BankStatements/`)

The newly introduced `BankStatement` **must be visible in the Filament admin panel** so financial administration can inspect imported statements.

- `BankStatementResource.php` — model `BankStatement`, `BookkeepingCluster`, `NavigationGroup::Bookkeeping`, pages `index`/`view` (no create/edit — statements are created by import; delete only if audit policy permits, prefer none).
- `Tables/BankStatementsTable.php` — owning account (`bankAccount.name`), `statement_number`, `start_date`/`end_date`, `opening_balance`, `closing_balance`, `currency`, `integrity_status` badge, `chain_status` badge, transaction count. Filters by account.
- `Pages/ListBankStatements.php`, `ViewBankStatement.php` (header actions none; read-only).
- `RelationManagers/BankStatementTransactionsRelationManager.php` — relationship `transactions`; read-only list (`date`, `description`, `amount`, counterparty `banking_account_number`) with row links to `BankingTransactionResource::getUrl('view', ...)` so users can inspect every imported transaction.
- `Schemas/BankStatementInfolist.php` — read-only metadata (account, number, period, balances, currency, retained `file_path` + download action guarded by `view_bank_statements`).

### 8.3 Balance overview page (`app/Filament/Admin/Pages/BankAccountBalanceOverview.php`)

Mirror `CostCenterResults.php` (a `Page implements HasForms, HasTable` inside `BookkeepingCluster`):

- Year selector + optional account filter.
- Table: account, opening balance, inflow (Σ positive `amount`), outflow (Σ negative `amount`), net movement, expected closing balance, last statement date, statement integrity status, statement chain status.
- Show "missing opening balance" and discrepancies prominently (warning/danger colors); never present a broken-chained account as reconciled.
- `canAccess()` guarded by `view_any_bank_accounts`.

### 8.4 Existing banking UI scoping

- `Schemas/BankingTransactionForm.php:19-43` — add a `Select` for owning `BankAccount` (relationship `bankAccount`, required on create); keep the separate counterparty IBAN field (relabel it clearly as counterparty); statement field read-only/empty for manual entries.
- `Pages/CreateBankingTransaction.php:22-43` — include `bank_account_id` in the hash and persistence.
- `Tables/BankingTransactionsTable.php:23-119` — add owning-account column (`bankAccount.name`), an account `SelectFilter` (relationship `bankAccount`, `searchable`, `preload`), a statement link column, and scope `Sum`/`RunningTotalSummery`/`MonthGroup` totals to the selected account. Keep the existing counterparty column clearly labeled.
- `Tables/RunningTotalSummery.php:14-35` — add `where('bank_account_id', ...)` scoping (read the selected account from the table filter state or query context).
- `Widgets/BankingTransactionStats.php` — scope/show per-account breakdown.
- `Pages/ListBankingTransactions.php:25-57` — preserve private upload storage; surface import validation/unknown-account/integrity warnings; dispatch `MatchBankingTransactionsJob` only after a successful import.
- `Pages/ViewBankingTransaction.php:22-75` — show owning account + statement metadata; add transfer link/pair actions alongside existing reversal/invoice/PO/bookkeeping relation managers.

---

## 9. Authorization, seed/configuration & documentation

- Add six CRUD permissions for `bank_accounts` and six for `bank_statements` to `app/Domain/Authorization/ResourcePermission.php` using existing TitleCase naming (`ViewAnyBankAccounts`, …, `DeleteAnyBankStatements`).
- Add `app/Policies/BankAccountPolicy.php` and `app/Policies/BankStatementPolicy.php` extending `ResourcePolicy` with `permissionPrefix()` `bank_accounts` / `bank_statements` (auto-discovered by Laravel policy naming).
- Add both `allPermissionsFor(...)` sets to `database/seeders/RolePermissionSeeder.php:70-97` (`seedFinancialAdministration()` only). Do not grant them to member/activity/rental/technical roles.
- Add an idempotent seeder (or extend an existing one) that `firstOrCreate`s a `BankAccount` from `config('sepa.creditor_iban')` + `config('sepa.creditor_bic')` with normalized IBAN. Do not invent a savings IBAN or opening balance.
- Add Dutch labels in `lang/nl/labels.php`: `bank_account`, `bank_accounts`, `bank_statement`, `bank_statements`, `opening_balance`, `closing_balance`, `statement_number`, `integrity_status`, `chain_status`, `expected_closing_balance`, `inflow`, `outflow`, `internal_transfer`, `unknown_bank_account` (message), `balance_overview`.
- Update `GenerateMt940Command.php:15-120` and `tests/Fixtures/mt940/*` so dev files emit configurable/multiple `:25:` accounts, sequential `:28C:`, valid `:60F:`/`:62F:` chains, and can produce mismatch/unknown-account fixtures.
- Update docs: `.agents/docs/banking/CONTEXT.md`, `.agents/docs/bookkeeping/CONTEXT.md`, `.agents/docs/GLOSSARY.md`, `.agents/docs/CONTEXT-MAP.md` — add `BankAccount`, `BankAccountYearBalance`, `BankStatement`, owning vs. counterparty IBAN, statement integrity/chain checks, internal transfers, opening/closing balance vocabulary, seven-year retention. Update `TODO.md` items 5 & 6.

---

## 10. Tests

Use PHPUnit classes (`php artisan make:test --phpunit`), `FeatureTestCase`/`UnitTestCase`, factories, `WithAuthorizedUser`, `Livewire::test`, and expectation classes for mocked arguments (see `tests/Unit/Domain/BankTransactions/BankTransactionRepositoryExpectation.php`). Extend/add:

1. `tests/Feature/Models/BankAccountTest.php` — IBAN normalization/unique, relationships, account-year uniqueness, statement uniqueness, nullable manual statement relation, casts/factory states.
2. `tests/Feature/Infrastructure/BankTransactions/BankTransactionImportServiceImplTest.php` (extend) — account resolution, FK persistence, statement metadata/file path, unknown-account atomic rejection, duplicate idempotency, multi-account/multi-statement files, internal mismatch status, chain-break status, retained source file.
3. `tests/Feature/Infrastructure/BankTransactions/BankTransactionDbRepositoryTest.php` (extend) — DTO persistence, account-scoped matching/reversal behavior, no cross-account matches, unchanged completion/bookkeeping behavior.
4. `tests/Feature/Infrastructure/BankAccounts/BankAccountBalanceRepositoryTest.php` — opening balance lookup, missing-opening state, signed sums, year/account isolation, expected closing balance, statement internal and chain checks.
5. `tests/Feature/Infrastructure/BankAccounts/InternalTransferTest.php` — own-IBAN detection, no invoice/PO proposals, valid pair/link idempotency, no self/cross-account links, zero net effect, no cost-center/bookkeeping record, partial pair visibility.
6. `tests/Feature/Filament/BankAccounts/BankAccountResourceTest.php` — Financial Administration CRUD, normalized duplicate validation, active/deactivate, year-balance relation manager CRUD/uniqueness, statement visibility.
7. `tests/Feature/Filament/BankStatements/BankStatementResourceTest.php` — list/view authorization, metadata/status rendering, statement transaction relation manager and navigation to imported transactions.
8. Extend `tests/Feature/Filament/BankingTransaction/BankingTransactionResourceTest.php` — manual account selection/hash, owning/counterparty display, account filter, account-scoped month/amount/running totals, statement link, existing actions.
9. Extend `tests/Feature/Authorization/AuthorizationTest.php` — Financial Administration access to both resources/balance page and denial for other roles/no role; preserve completed-transaction protections.
10. `tests/Feature/Database/BankAccountBackfillTest.php` — legacy rows receive configured checking account, hashes and links survive, nullability transition occurs only after successful backfill.

Cover both PostgreSQL (configured DB) and the SQLite test path for date/year/month expressions, foreign keys, unique constraints, and summary queries (use `whereYear`/`whereBetween` which are portable, and driver-aware raw expressions only where existing code already does).

---

## 11. Delivery order & verification

Implement in dependency order: domain terminology/contracts → migrations/backfill/models/factories → repositories/reconciliation service → import/repository/matching → transfer handling → Filament resources/pages → existing UI scoping → authorization/translations/docs/fixtures → tests.

Run targeted tests after each layer, then the required project checks through the repository Taskfile:

```bash
./Taskfile fix
./Taskfile stan
./Taskfile lint
./Taskfile test
```

Resolve all formatting (mago), PHPStan (domain/infrastructure/tests configs), lint, and test failures before completion. Manually verify a financial administrator can create accounts, enter yearly opening balances, upload statements, view statement metadata and imported lines, see flagged gaps, and inspect account-isolated totals.

---

## 12. Non-goals & risks

- No full grootboek/double-entry rewrite, chart of accounts, journal entries, VAT redesign, or account column on `BookkeepingRecord` in this feature.
- Do not auto-create unknown accounts, silently discard statement metadata, or combine checking and savings totals.
- Existing `banking_account_number` naming is ambiguous; document it as counterparty IBAN and defer a physical rename to a separate compatibility migration.
- Decide before coding whether an internal statement mismatch is flagged-and-imported (the default) or rejects the file; whichever is chosen must be consistent across import behavior, status UI, and tests.
- Historical data cannot prove chain completeness before the first retained statement; expose that baseline limitation rather than claiming unavailable coverage.
