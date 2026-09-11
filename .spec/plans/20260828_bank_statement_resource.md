# Plan: Bank Statement Resource in Filament

**Date**: 2026-08-28  
**Goal**: Add a read-only `BankStatementResource` to the Filament admin panel so financial administrators can import MT940 files and inspect imported bank statements, see their opening/closing balances and period, and see what percentage of the statement’s total transaction amount has been matched. From the statement detail view they can manage the contained transactions and attach/match invoices, purchase orders, and bookkeeping records.

---

## 1. Domain context & assumptions

- `BankStatement` already exists in `app/Models/BankStatement.php` with `bankAccount()` and `transactions()` relations, and casts for dates, balances, `StatementIntegrityStatus`, and `StatementChainStatus`.
- `BankingTransaction` already exists and has a `bankStatement()` BelongsTo relation plus existing relations for `invoices`, `purchaseOrders`, and `bookkeepingRecords`.
- The existing `BankingTransactionResource` already provides a full view page (`ViewBankingTransaction`) with relation managers and actions to attach/detach invoices, purchase orders, and bookkeeping records, and to complete the transaction.
- Statements are created exclusively by the MT940 import flow; the UI is read-only (no create/edit).
- All new Filament resources live under `app/Filament/Admin/Resources/`, follow the split `Resource`/`Schemas`/`Tables`/`Pages`/`RelationManagers` convention, and belong to the existing `BookkeepingCluster` (`app/Filament/Admin/Clusters/Bookkeeping/BookkeepingCluster.php`).

### Key definition

**Matched percentage**: the ratio of the sum of each contained transaction’s `matched_amount` to the sum of the absolute value of each transaction’s `amount`, expressed as a percentage. For an empty statement the value is `null`/`—`.

---

## 2. Files to create

| File | Purpose |
|------|---------|
| `app/Filament/Admin/Resources/BankStatements/BankStatementResource.php` | Resource registration, cluster, navigation, label, pages |
| `app/Filament/Admin/Resources/BankStatements/Tables/BankStatementsTable.php` | List columns: account, number, period, balances, matched percentage, status badges |
| `app/Filament/Admin/Resources/BankStatements/Pages/ListBankStatements.php` | List page (no create action; statements are import-only) |
| `app/Filament/Admin/Resources/BankStatements/Pages/ViewBankStatement.php` | View page with combined relation-manager tabs |
| `app/Filament/Admin/Resources/BankStatements/RelationManagers/BankStatementTransactionsRelationManager.php` | Lists the statement’s transactions and links each one to its management view |
| `app/Filament/Admin/Resources/BankStatements/Schemas/BankStatementInfolist.php` | Read-only detail metadata (account, number, dates, balances, matched percentage, currency, integrity/chain status, file path) |
| `app/Filament/Admin/Resources/BankStatements/Actions/CompleteAllMatchedAction.php` | Header action that completes all fully matched transactions in the statement |
| `app/Filament/Admin/Actions/ImportMt940Action.php` | Shared MT940 import action used by both banking resources |
| `app/Policies/BankStatementPolicy.php` | Authorization policy extending `ResourcePolicy` |

## 3. Files to modify

| File | Change |
|------|--------|
| `app/Models/BankStatement.php` | Add accessor/attribute for `matched_percentage` |
| `app/Filament/Admin/Resources/BankingTransactions/Pages/ListBankingTransactions.php` | Replace inline `importMt940` action with shared `ImportMt940Action` |
| `app/Domain/Authorization/ResourcePermission.php` | Add six CRUD permissions for `bank_statements` |
| `database/seeders/RolePermissionSeeder.php` | Grant all `bank_statements` permissions to `RoleName::FinancialAdministration` only |
| `lang/nl/labels.php` | Add Dutch labels for statement fields and the matched percentage |
| `tests/Feature/Authorization/AuthorizationTest.php` | Add assertion that financial administration can view bank statements and other roles cannot |
| `tests/Feature/Filament/BankStatements/BankStatementResourceTest.php` | New feature test class |

---

## 4. Model changes

### `app/Models/BankStatement.php`

Add a computed accessor that returns the matched percentage. It loads the statement’s transactions and compares the sum of their `matched_amount` to the sum of their absolute `amount`.

```php
use App\Models\BankingTransaction;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * @property-read float|null $matched_percentage
 */

/**
 * @return Attribute<float|null, never>
 */
protected function matchedPercentage(): Attribute
{
    return Attribute::get(function (): ?float {
        $transactions = $this->transactions;

        if ($transactions->isEmpty()) {
            return null;
        }

        $totalAmount = $transactions->sum(static fn (BankingTransaction $t): float => abs($t->amount));

        if ($totalAmount < 0.01) {
            return null;
        }

        $totalMatched = $transactions->sum(static fn (BankingTransaction $t): float => $t->matched_amount);

        return round(($totalMatched / $totalAmount) * 100, 2);
    });
}
```

**Note**: This accessor loads all transactions into memory. In `BankStatementsTable::configure()` eager-load them with `->modifyQueryUsing(fn ($query) => $query->with('transactions'))`. For very large statements this may become heavy; if performance becomes an issue, replace the accessor with a dedicated repository query or a materialized column in a follow-up iteration.

---

## 5. Resource, table, and pages

### 5.1 `BankStatementResource.php`

Mirror `BankAccountResource.php` and `BankingTransactionResource.php`.

- `$model = BankStatement::class`
- `$navigationIcon = Heroicon::DocumentCurrencyEuro` or `Heroicon::DocumentText` (pick an icon consistent with financial documents)
- `$navigationGroup = NavigationGroup::Bookkeeping`
- `$cluster = BookkeepingCluster::class`
- `$navigationSort = 3` (between BankingTransactions at 2 and BankAccounts at 5)
- `$recordTitleAttribute = 'statement_number'`
- `getLabel()` → `__('labels.bank_statement')`
- `getPluralLabel()` → `__('labels.bank_statements')`
- `form()` → use `BankStatementForm` only if needed; because the resource is read-only, the form schema is not required for the UI. Provide an empty/null schema or skip.
- `table()` → `BankStatementsTable::configure($table)`
- `getPages()`:
  ```php
  [
      'index' => ListBankStatements::route('/'),
      'view' => ViewBankStatement::route('/{record}'),
  ]
  ```

### 5.2 `BankStatementsTable.php`

Columns:

1. `TextColumn::make('bankAccount.name')` → `__('labels.bank_account')`, searchable, sortable.
2. `TextColumn::make('statement_number')` → `__('labels.statement_number')`, searchable, sortable.
3. `TextColumn::make('start_date')` → `__('labels.start_date')`, date, sortable.
4. `TextColumn::make('end_date')` → `__('labels.end_date')`, date, sortable.
5. `TextColumn::make('opening_balance')` → `__('labels.opening_balance')`, `money('EUR')`, alignEnd, sortable.
6. `TextColumn::make('closing_balance')` → `__('labels.closing_balance')`, `money('EUR')`, alignEnd, sortable.
7. `TextColumn::make('matched_percentage')` → `__('labels.matched_percentage')`, suffix('%'), alignEnd, sortable, color based on value (e.g. `< 100` warning, `100` success, `null` gray).
   - Format `null` as `—`.
   - Eager-load `transactions` via `->modifyQueryUsing(fn ($query) => $query->with('transactions'))`.
8. `TextColumn::make('integrity_status')` → `__('labels.integrity_status')`, badge, color map (`Valid` → success, `Mismatch` → danger).
9. `TextColumn::make('chain_status')` → `__('labels.chain_status')`, badge, color map (`Baseline` → gray, `Ok` → success, `Broken` → danger).
10. `TextColumn::make('currency')` → `__('labels.currency')`, toggleable.

Filters:

- `SelectFilter::make('bank_account')` on relationship `bankAccount`, searchable, preload.
- Date range filter on `start_date`/`end_date` (reuse existing date filter patterns if any).

Toolbar actions: only `DeleteBulkAction` if delete is permitted by policy. No create action here (statements are import-only).

Default sort: `start_date` descending.

### 5.3 `ListBankStatements.php`

Statements are created by MT940 import, so the list page offers the import action instead of a create action. Use the shared `ImportMt940Action`; do not duplicate the import logic.

```php
use App\Filament\Admin\Actions\ImportMt940Action;

final class ListBankStatements extends ListRecords
{
    protected static string $resource = BankStatementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportMt940Action::make(),
        ];
    }
}
```

### 5.4 `ViewBankStatement.php`

- Extends `Filament\Resources\Pages\ViewRecord`.
- Header actions:
  - `CompleteAllMatchedAction::make()` — completes every contained transaction whose status is `Open` and whose `unmatched_amount` is fully matched (`abs($t->unmatched_amount) < 0.01`). See §9.
  - Optional `Action::make('downloadMt940')` — download the original MT940 file, guarded by `Gate::allows('view', $this->record)`.
- `getRelationManagers()` returns `BankStatementTransactionsRelationManager::class`.
- `hasCombinedRelationManagerTabsWithContent(): true`
- `getContentTabLabel(): __('labels.bank_statement')`
- Use `BankStatementInfolist` as the main content schema (see §6).

---

## 6. Infolist schema

### `BankStatementInfolist.php`

Read-only metadata using `Filament\Infolists\Infolist` (Filament v5). If the project does not use infolists for view pages yet, mirror the `BankingTransactionForm` shape but with `TextEntry` components.

Sections:

1. **Statement information**
   - `TextEntry::make('bankAccount.name')` → bank account name + IBAN
   - `TextEntry::make('statement_number')`
   - `TextEntry::make('start_date')` → date
   - `TextEntry::make('end_date')` → date
   - `TextEntry::make('currency')`
2. **Balances**
   - `TextEntry::make('opening_balance')` → money('EUR')
   - `TextEntry::make('closing_balance')` → money('EUR')
3. **Matching progress**
   - `TextEntry::make('matched_percentage')` → suffix('%'), color based on value, format `null` as `—`
4. **Status**
   - `TextEntry::make('integrity_status')` → badge
   - `TextEntry::make('chain_status')` → badge
5. **Source file**
   - `TextEntry::make('file_path')`
   - Add a `download` action if supported by the infolist component, or keep it as a header action on the page.

---

## 7. Transaction relation manager

### `BankStatementTransactionsRelationManager.php`

- `$relationship = 'transactions'`
- No `$relatedResource` because the related model is `BankingTransaction`, which does have a resource; set it to `BankingTransactionResource::class` only if it improves the default URL behavior.

Table columns:

1. `TextColumn::make('date')` → date, sortable
2. `TextColumn::make('description')` → searchable, limit(60)
3. `TextColumn::make('amount')` → money('EUR'), alignEnd, color based on sign
4. `TextColumn::make('banking_account_number')` → counterparty IBAN, searchable
5. `TextColumn::make('status')` → badge (`Open`/`Completed`)
6. `TextColumn::make('resolve_status')` → badge (`Unresolved`/`Resolved`/`Unresolvable`)
7. `TextColumn::make('unmatched_amount')` → money('EUR'), alignEnd, color warning when non-zero

Row behavior:

- Make the row (or the description column) link to `BankingTransactionResource::getUrl('view', ['record' => $record])` so the user can open the transaction’s own detail page where all attach/match/complete actions already exist.
- Alternatively, add a `recordAction` named `manageTransaction` that navigates to the view page.

Header actions: none (transactions cannot be added to a statement manually; they are created by import).

Record actions:

- `Action::make('view')` or use `recordUrl(ViewOrEdit::route(BankingTransactionResource::class))`. This is the primary way to manage matching from the statement view.
- Optional: `Action::make('quickComplete')` visible only when the transaction is open, unresolved/resolved, and `abs($record->unmatched_amount) < 0.01`, dispatching `BankTransactionService::complete(BankTransactionId::create($record->id))`. This is a convenience shortcut but is **optional**; the transaction view already has it.

The relation manager should **not** duplicate the full attach actions, because the existing `ViewBankingTransaction` page already provides attach/detach for invoices, purchase orders, and bookkeeping records, plus completion. The statement view is a gateway to that page.

---

## 8. Shared MT940 import action

### `app/Filament/Admin/Actions/ImportMt940Action.php`

Extract the inline `importMt940` action from `ListBankingTransactions.php` into a reusable action class so both `ListBankingTransactions` and `ListBankStatements` use the same implementation.

```php
use App\Domain\BankTransactions\BankTransactionImportService;
use App\Domain\Jobs\MatchBankingTransactionsJob;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

final class ImportMt940Action
{
    public static function make(): Action
    {
        return Action::make('importMt940')
            ->label(__('labels.import_mt940'))
            ->modalHeading(__('labels.import_mt940'))
            ->schema([
                FileUpload::make('mt940_file')
                    ->label(__('labels.mt940_file'))
                    ->directory('mt940-imports')
                    ->disk('local')
                    ->required(),
            ])
            ->action(static function (Page $livewire, array $data, BankTransactionImportService $importService): void {
                $result = $importService->importFromFile(
                    storage_path('app/private/' . $data['mt940_file']),
                );

                Notification::make()
                    ->title(__('labels.import_complete'))
                    ->body(__('labels.import_result', [
                        'imported' => $result['imported'],
                        'skipped' => $result['skipped'],
                    ]))
                    ->success()
                    ->send();

                if ($result['imported'] > 0) {
                    MatchBankingTransactionsJob::dispatch();
                }

                $livewire->dispatch('refreshTable');
            });
    }
}
```

### Refactor `ListBankingTransactions.php`

Replace the inline action with the shared class:

```php
use App\Filament\Admin\Actions\ImportMt940Action;

protected function getHeaderActions(): array
{
    return [
        ImportMt940Action::make(),
        CreateAction::make(),
    ];
}
```

Remove the now-unused imports (`BankTransactionImportService`, `MatchBankingTransactionsJob`, `FileUpload`, `Notification`, `Page`) from `ListBankingTransactions.php` unless still needed.

---

## 9. Complete all matched action

### `app/Filament/Admin/Resources/BankStatements/Actions/CompleteAllMatchedAction.php`

A header action on `ViewBankStatement` that completes every transaction in the statement that is open and fully matched.

```php
use App\Domain\BankTransactions\BankTransactionId;
use App\Domain\BankTransactions\BankTransactionService;
use App\Domain\BankTransactions\BankTransactionStatus;
use App\Models\BankStatement;
use Filament\Actions\Action;

final class CompleteAllMatchedAction
{
    public static function make(): Action
    {
        return Action::make('completeAllMatched')
            ->label(__('labels.complete_all_matched'))
            ->modalHeading(__('labels.complete_all_matched'))
            ->color('success')
            ->icon('heroicon-o-check-circle')
            ->requiresConfirmation()
            ->action(static function (BankStatement $record, BankTransactionService $service): void {
                $record->transactions
                    ->filter(
                        static fn ($transaction): bool =>
                            $transaction->status === BankTransactionStatus::Open
                            && abs($transaction->unmatched_amount) < 0.01
                    )
                    ->each(static function ($transaction) use ($service): void {
                        $service->complete(BankTransactionId::create($transaction->id));
                    });
            })
            ->successNotificationTitle(__('labels.completed'))
            ->after(static fn (ViewBankStatement $livewire) => $livewire->dispatch('refresh'));
    }
}
```

**Behavior notes**:
- The action is always visible on the view page but completes only eligible transactions.
- If no eligible transactions exist, the action succeeds with no changes (or show an info notification — decide consistently with project patterns).
- Uses the existing `BankTransactionService::complete()` domain method, which throws `CouldNotCompleteTransaction` if the amount is not fully matched; the filter guards against this.
- Dispatch `refresh` so the infolist percentage, relation manager rows, and stats update.

---

## 10. Authorization

### 10.1 `ResourcePermission.php`

Add after the Banking Transactions block:

```php
// Bank Statements
case ViewAnyBankStatements = 'view_any_bank_statements';
case ViewBankStatements = 'view_bank_statements';
case CreateBankStatements = 'create_bank_statements';
case UpdateBankStatements = 'update_bank_statements';
case DeleteBankStatements = 'delete_bank_statements';
case DeleteAnyBankStatements = 'delete_any_bank_statements';
```

### 10.2 `BankStatementPolicy.php`

```php
final class BankStatementPolicy extends ResourcePolicy
{
    #[Override]
    protected static function permissionPrefix(): string
    {
        return 'bank_statements';
    }
}
```

Because statements are import-only, `create` and `update` will always be checked but will naturally return false for users who do not hold the permission. The seeder should not grant `create`/`update` to financial administration either, unless the business explicitly wants statement deletion. The plan recommends granting only view/delete permissions for safety:

- `view_any_bank_statements`
- `view_bank_statements`
- `delete_bank_statements`
- `delete_any_bank_statements`

### 10.3 `RolePermissionSeeder.php`

In `seedFinancialAdministration()`, add:

```php
$this->viewPermissionsFor('bank_statements'),
$this->viewPermissionsFor('bank_accounts'),
```

(Also add `bank_accounts` view permissions here if not already present; the existing `ViewBankAccount` page references missing relation managers, so this is a good opportunity to fix the seeder for that resource too.)

No other roles receive bank-statement access.

---

## 11. Labels

Add to `lang/nl/labels.php` (after the existing bank account labels around line 313):

```php
'bank_statement' => 'Bankafschrift',
'bank_statements' => 'Bankafschriften',
'statement_number' => 'Afschriftnummer',
'opening_balance' => 'Beginsaldo',
'closing_balance' => 'Eindsaldo',
'integrity_status' => 'Saldo controle',
'chain_status' => 'Aansluiting controle',
'matched_percentage' => 'Gekoppeld percentage',
'complete_all_matched' => 'Rond alle gekoppelde af',
'currency' => 'Valuta',
'manage_transaction' => 'Beheer transactie',
```

If the existing status enum labels are needed for `integrity_status` and `chain_status`, add nested arrays:

```php
'integrity_statuses' => [
    'valid' => 'Correct',
    'mismatch' => 'Verschil',
],
'chain_statuses' => [
    'baseline' => 'Startpunt',
    'ok' => 'Aansluitend',
    'broken' => 'Onderbroken',
],
```

---

## 12. Tests

Create `tests/Feature/Filament/BankStatements/BankStatementResourceTest.php` using PHPUnit.

### 12.1 List page

```php
public function test_list_page_is_accessible(): void
{
    $this->withAuthorizedUser();

    Livewire::test(ListBankStatements::class)
        ->assertSuccessful();
}
```

### 12.2 Lists bank statements

```php
public function test_can_list_bank_statements(): void
{
    $this->withAuthorizedUser();

    $statement = BankStatement::factory()->create();

    Livewire::test(ListBankStatements::class)
        ->assertCanSeeTableRecords([$statement]);
}
```

### 12.3 List page has MT940 import action

```php
public function test_list_page_has_mt940_import_action(): void
{
    $this->withAuthorizedUser();

    Livewire::test(ListBankStatements::class)
        ->assertActionVisible('importMt940');
}
```

### 12.4 Shows matched percentage

```php
public function test_matched_percentage_is_shown(): void
{
    $this->withAuthorizedUser();

    $statement = BankStatement::factory()->create();

    // Transaction with amount 100 and fully matched (invoice line of 100)
    $transaction = BankingTransaction::factory()
        ->forStatement($statement)
        ->create(['amount' => 100.00]);
    $invoice = Invoice::factory()->create();
    $invoice->lines()->create([
        'description' => 'Match',
        'price' => 100.00,
        'quantity' => 1,
        'vat' => 21.00,
        'cost_center_id' => CostCenter::factory(),
    ]);
    $transaction->invoices()->attach($invoice->id);

    // Transaction with amount 50 and unmatched
    BankingTransaction::factory()
        ->forStatement($statement)
        ->create(['amount' => 50.00]);

    Livewire::test(ListBankStatements::class)
        ->assertTableColumnStateSet('matched_percentage', 66.67, $statement);
}
```

Adjust the assertion delta to account for floating-point rounding in the accessor (`round(..., 2)`).

### 12.5 View page shows transactions

```php
public function test_view_page_shows_statement_transactions(): void
{
    $this->withAuthorizedUser();

    $statement = BankStatement::factory()->create();
    $transaction = BankingTransaction::factory()->forStatement($statement)->create();

    Livewire::test(ViewBankStatement::class, ['record' => $statement->id])
        ->assertSuccessful()
        ->assertSee($transaction->description);
}
```

### 12.6 Complete all matched action completes eligible transactions

```php
public function test_can_complete_all_matched_transactions(): void
{
    $this->withAuthorizedUser();

    $statement = BankStatement::factory()->create();
    $costCenter = CostCenter::factory()->create();

    $matched = BankingTransaction::factory()
        ->forStatement($statement)
        ->create(['amount' => 100.00, 'status' => BankTransactionStatus::Open]);
    $invoice = Invoice::factory()->create();
    $invoice->lines()->create([
        'description' => 'Match',
        'price' => 100.00,
        'quantity' => 1,
        'vat' => 21.00,
        'cost_center_id' => $costCenter->id,
    ]);
    $matched->invoices()->attach($invoice->id);

    $unmatched = BankingTransaction::factory()
        ->forStatement($statement)
        ->create(['amount' => 50.00, 'status' => BankTransactionStatus::Open]);

    Livewire::test(ViewBankStatement::class, ['record' => $statement->id])
        ->callAction('completeAllMatched')
        ->assertHasNoActionErrors();

    $matched->refresh();
    $unmatched->refresh();

    static::assertSame('completed', $matched->status->value);
    static::assertSame('open', $unmatched->status->value);
}
```

### 12.7 View page links to transaction management

```php
public function test_transaction_row_links_to_banking_transaction_view(): void
{
    $this->withAuthorizedUser();

    $statement = BankStatement::factory()->create();
    $transaction = BankingTransaction::factory()->forStatement($statement)->create();

    Livewire::test(BankStatementTransactionsRelationManager::class, [
        'ownerRecord' => $statement,
        'pageClass' => ViewBankStatement::class,
    ])
        ->assertSuccessful()
        ->assertSee($transaction->description)
        ->assertSee(BankingTransactionResource::getUrl('view', ['record' => $transaction]));
}
```

### 12.8 Authorization

In `AuthorizationTest.php`:

```php
public function test_financial_administration_can_view_bank_statements(): void
{
    $this->withUserHavingRole(RoleName::FinancialAdministration);

    Livewire::test(ListBankStatements::class)
        ->assertSuccessful();
}

public function test_member_administration_cannot_view_bank_statements(): void
{
    $this->withUserHavingRole(RoleName::MemberAdministration);

    Livewire::test(ListBankStatements::class)
        ->assertForbidden();
}
```

---

## 13. Dependencies and blockers

1. `ViewBankAccount.php` currently references two missing classes:
   - `App\Filament\Admin\Resources\BankAccounts\RelationManagers\BankAccountStatementsRelationManager`
   - `App\Filament\Admin\Resources\BankAccounts\RelationManagers\BankAccountYearBalancesRelationManager`
   This will cause runtime errors when opening a bank account view. Either implement those relation managers as part of this work or temporarily remove the references. The recommended path is to implement `BankAccountStatementsRelationManager` so that bank account views list their statements and link to the new `BankStatementResource::view` page.

2. `BankAccountPolicy` does not exist. Create it (extending `ResourcePolicy` with prefix `bank_accounts`) before adding bank account permissions to the seeder, otherwise policy auto-discovery will fail.

3. Bank statement permissions must be seeded before the new resource is accessible. Run `php artisan db:seed --class=RolePermissionSeeder` (or rely on tests reseeding).

---

## 14. Delivery order

1. Add `ResourcePermission` cases.
2. Create `BankStatementPolicy` and `BankAccountPolicy`.
3. Update `RolePermissionSeeder`.
4. Add labels.
5. Add `matchedPercentage` accessor to `BankStatement`.
6. Create shared `ImportMt940Action` and refactor `ListBankingTransactions.php` to use it.
7. Create `BankStatementResource`, `BankStatementsTable`, `ListBankStatements`, `ViewBankStatement`, `BankStatementInfolist`, `BankStatementTransactionsRelationManager`, and `CompleteAllMatchedAction`.
8. Optionally create/fix `BankAccountStatementsRelationManager`.
9. Write feature tests.
10. Run `./Taskfile test`, `./Taskfile stan`, `./Taskfile lint`, `./Taskfile fix`.

---

## 15. Verification commands

```bash
./Taskfile artisan test --filter=BankStatementResourceTest
./Taskfile artisan test --filter=AuthorizationTest
./Taskfile stan
./Taskfile lint
./Taskfile fix
```

---

## 16. Non-goals

- Do **not** add create/edit forms for statements; statements remain import-only.
- Do **not** reimplement the attach/detach/complete actions for transactions; reuse the existing `BankingTransactionResource` view page.
- Do **not** change the MT940 import flow or statement creation logic.
- Do **not** alter the `BankingTransaction` matching/completion domain behavior.
