# Context Boundary Alignment

Architecture pass, 2026-10-11. The code is the source of truth for the domain model; the docs below are aligned to it, plus four code moves where the current structure is wrong. Execute code moves first, then docs, then verification.

## Target context model

| Context | Code location |
|---|---|
| Members (includes Registration) | `app/Domain/Members`, `app/Domain/Registration` |
| Billing | `app/Domain/Billing` (new — promoted out of Invoices) |
| Invoicing | `app/Domain/Invoices` |
| Banking (one context, three modules) | `app/Domain/BankAccounts`, `app/Domain/BankStatements`, `app/Domain/BankTransactions` |
| Purchase Orders | `app/Domain/PurchaseOrders` |
| Storage Space Rentals | `app/Domain/StorageSpaceRentals` |
| Activities | `app/Domain/Activities` |
| Bookkeeping | `app/Domain/Bookkeeping` |
| Mail | `app/Domain/Mail` |
| Shared Kernel | `app/Domain/SharedKernel` (new) |

Not contexts: `Authorization` (enums only) and `Jobs` (generic queue seam) are technical support modules. Inventory has no domain logic.

Context names need not match code namespaces — the map must state each context's code location.

## Code moves

- [x] **1. Promote Billing out of Invoices.**
  - `git mv app/Domain/Invoices/Billing app/Domain/Billing`
  - `git mv app/Infrastructure/Invoices/Billing app/Infrastructure/Billing`
  - `git mv tests/Unit/Domain/Invoices/Billing tests/Unit/Domain/Billing`
  - `git mv tests/Feature/Infrastructure/Invoices/Billing tests/Feature/Infrastructure/Billing`
  - Replace everywhere in `app/`, `tests/`, `database/`:
    - `App\Domain\Invoices\Billing` → `App\Domain\Billing`
    - `App\Infrastructure\Invoices\Billing` → `App\Infrastructure\Billing`
    - `Tests\Unit\Domain\Invoices\Billing` → `Tests\Unit\Domain\Billing`
    - `Tests\Feature\Infrastructure\Invoices\Billing` → `Tests\Feature\Infrastructure\Billing`

- [x] **2. Move `CostCenterId` to Bookkeeping.** (after step 1)
  - `git mv app/Domain/Billing/CostCenterId.php app/Domain/Bookkeeping/CostCenterId.php`
  - `git mv tests/Unit/Domain/Billing/CostCenterIdTest.php tests/Unit/Domain/Bookkeeping/CostCenterIdTest.php`
  - Replace `App\Domain\Billing\CostCenterId` → `App\Domain\Bookkeeping\CostCenterId` in `app/`, `tests/`.

- [x] **3. Move `CompoundPrice` to the Shared Kernel.**
  - `mkdir -p app/Domain/SharedKernel tests/Unit/Domain/SharedKernel`
  - `git mv app/Domain/Invoices/CompoundPrice.php app/Domain/SharedKernel/CompoundPrice.php`
  - `git mv tests/Unit/Domain/Invoices/CompoundPriceTest.php tests/Unit/Domain/SharedKernel/CompoundPriceTest.php`
  - Replace `App\Domain\Invoices\CompoundPrice` → `App\Domain\SharedKernel\CompoundPrice` in `app/`, `tests/`.
  - Future cross-context value objects also go here; `app/Domain/NumericId.php` stays where it is (out of scope).

- [x] **4. Move the banking job into its context.**
  - `mkdir -p app/Domain/BankTransactions/Jobs tests/Unit/Domain/BankTransactions/Jobs`
  - `git mv app/Domain/Jobs/MatchBankTransactionsJob.php app/Domain/BankTransactions/Jobs/MatchBankTransactionsJob.php`
  - `git mv tests/Unit/Domain/Jobs/MatchBankTransactionsJobTest.php tests/Unit/Domain/BankTransactions/Jobs/MatchBankTransactionsJobTest.php`
  - Replace `App\Domain\Jobs\MatchBankTransactionsJob` → `App\Domain\BankTransactions\Jobs\MatchBankTransactionsJob` (callers: `app/Console/Commands/MatchBankTransactionsCommand.php`, `app/Filament/Admin/Actions/ImportMt940Action.php`, tests).
  - Keep `app/Domain/Jobs` as the generic queue seam; any context with a job may add its own `Jobs/` folder.

## Documentation

- [x] **5. `/.agents/docs/CONTEXT-MAP.md`**
  - Contexts: add Billing (promoted) and Shared Kernel; fold Registration into Members; make Banking one context spanning `BankAccounts`/`BankStatements`/`BankTransactions`; state the code location for every context.
  - Technical support modules: Authorization stays standalone (not a Members concern); Jobs stays the generic seam.
  - Relationships: make Billing first-class; add Billing → Bookkeeping (`CostCenterId`) and Invoices/Billing/PurchaseOrders → Shared Kernel (`CompoundPrice`); record the cross-context rule (below) and keep the existing cycles.

- [x] **6. Context docs**
  - `billing/CONTEXT.md`: drop `CostCenter` and `CostCenterBudget`; add the undocumented `ApplyStoppedMembership` applicator.
  - `bookkeeping/CONTEXT.md`: add `CostCenter` (owned here) and `CostCenterBudget`; update the intro — it owns CostCenter + budgets, so it is not purely a read-side projection.
  - `members/CONTEXT.md`: remove "and authorization"; keep the Registration term.
  - `invoicing/CONTEXT.md`: note `CompoundPrice` now lives in the Shared Kernel.

- [x] **7. `/.agents/docs/GLOSSARY.md`**
  - `CostCenter`: owner → Bookkeeping (was Billing).
  - `CompoundPrice`: owner → Shared Kernel (`app/Domain/SharedKernel`).
  - `BillableItemInstance`: reword as a persisted subscription record (no domain entity yet), owned by Billing.

- [x] **8. `/.agents/docs/ADR-MAP.md`**
  - Amend `0001-hexagonal-architecture`: replace "no Laravel dependencies" with the real allowlist policy (`Illuminate\Contracts`, Mailables, Collection/Enumerable/Str/Arr/LazyCollection, Carbon, Psr, Ramsey, Webmozart, JeroenG Autowire) plus per-file `@phpstan-ignore domain.dependency` escapes tracked as debt.
  - Add `0005-billing-context`: Billing is its own bounded context, promoted out of Invoices.
  - Add `0006-cross-context-dependencies`: contexts couple only through published ports, typed IDs, value objects and DTOs — never concrete classes or Eloquent models; cross-context value objects live in the Shared Kernel.
  - Add `0007-filament-admin-adapter`: Filament reads Eloquent models directly for CRUD/listing; state-changing operations go through domain ports.
  - Add `0008-cost-center-ownership`: CostCenter and CostCenterBudget are owned by Bookkeeping.

## Verification

- [x] `grep -rn "Invoices\\\\Billing\|Domain\\\\Invoices\\\\CompoundPrice\|Domain\\\\Jobs\\\\MatchBankTransactionsJob" app tests` returns nothing.
- [x] `./Taskfile artisan optimize:clear` (drop the cached autowire map).
- [x] `./Taskfile stan` passes (domain purity + `UnitTestRequired`).
- [x] `./Taskfile test` passes.
- [x] `./Taskfile lint` passes.
