# Rename `BankingTransaction` to `BankTransaction`

- **Date:** 2026-09-18
- **Subject:** Rename `App\Models\BankingTransaction` (and all closely related application classes) to `BankTransaction`, aligning the model name with the existing `BankAccount` and `BankStatement` naming in the Banking context.

## Current situation

The codebase uses `BankingTransaction` as the Eloquent model name while the surrounding domain already uses `BankTransaction` for statuses, services, repositories, and IDs (`BankTransactionStatus`, `BankTransactionService`, `BankTransactionRepository`, `BankTransactionId`). This inconsistency leaks into filenames, namespaces, permissions, translations, and tests. The model file is `app/Models/BankingTransaction.php` and the Filament resource lives under `app/Filament/Admin/Resources/BankingTransactions/`.

There are ~840 textual matches for `Banking` in the codebase, but many are unrelated and must stay untouched:

- External MT940 parser: `Kingsquare\Banking\*` and `Kingsquare\Parser\Banking\Mt940`.
- Member registration SEPA payment fields: `PaymentInfoData::bankingAccount`, `bankingBic`, `bankingAccountHolderName` and the corresponding request/validation attributes and Blade views.
- The bounded context name "Banking" in `.agents/docs/`.

This plan scopes the rename to the transaction model and classes that directly represent or operate on it.

## Planned changes summary

- Rename `App\Models\BankingTransaction` → `App\Models\BankTransaction` (file + class).
- Keep the database table name `banking_transactions` by setting `protected $table` explicitly; this avoids a risky migration of existing tables, foreign keys, and pivot tables.
- Rename the closely related domain/application classes:
  - `BankingTransactionReversalState` → `BankTransactionReversalState`
  - `BankingTransactionPolicy` → `BankTransactionPolicy`
  - `BankingTransactionFactory` → `BankTransactionFactory`
  - `MatchBankingTransactionsJob` → `MatchBankTransactionsJob`
  - `MatchBankingTransactionsCommand` → `MatchBankTransactionsCommand`
  - Filament resource namespace `BankingTransactions` → `BankTransactions` and all page/action/relation-manager/schema/table/widget classes.
- Update every import, type hint, generic docblock, relation definition, and factory usage from `BankingTransaction` to `BankTransaction`.
- Update `ResourcePermission` enum cases and the seeder string from `banking_transactions` to `bank_transactions`.
- Update Dutch translation keys in `lang/nl/labels.php`.
- Rename and update affected test files and namespaces.
- Update `.agents/docs/banking/CONTEXT.md` and `.agents/docs/GLOSSARY.md` terminology.
- Verify with static analysis and the relevant feature/unit tests.

## Detailed plan

### Rename the Eloquent model and preserve the table name

Rename `app/Models/BankingTransaction.php` to `app/Models/BankTransaction.php` and change the class name. Because the class name changes from `BankingTransaction` to `BankTransaction`, Laravel would infer the table `bank_transactions`. To avoid a destructive database migration, explicitly keep the existing table name.

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\BankTransactions\BankTransactionReversalState;
use App\Domain\BankTransactions\BankTransactionStatus;
use App\Domain\BankTransactions\ResolveStatus;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\DB;
use Override;

/**
 * @property int $id
 * @property string $description
 * @property float $amount
 * @property BankTransactionStatus $status
 * @property ResolveStatus $resolve_status
 * @property DateTimeInterface $date
 */
#[Guarded(['id', 'created_at', 'updated_at'])]
final class BankTransaction extends Model
{
    use HasFactory;

    protected $table = 'banking_transactions';

    /** @return MorphToMany<Invoice, $this> */
    public function invoices(): MorphToMany
    {
        return $this->morphedByMany(Invoice::class, 'reference', 'banking_transaction_references')
            ->withTimestamps();
    }

    /** @return MorphToMany<PurchaseOrder, $this> */
    public function purchaseOrders(): MorphToMany
    {
        return $this->morphedByMany(PurchaseOrder::class, 'reference', 'banking_transaction_references')
            ->withTimestamps();
    }

    /** @return HasMany<BookkeepingRecord, $this> */
    public function bookkeepingRecords(): HasMany
    {
        return $this->hasMany(BookkeepingRecord::class);
    }

    /** @return BelongsTo<BankTransaction, $this> */
    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversed_by_transaction_id');
    }

    /** @return HasOne<BankTransaction, $this> */
    public function reversedTransaction(): HasOne
    {
        return $this->hasOne(self::class, 'reversed_by_transaction_id');
    }

    /** @return BelongsTo<BankAccount, $this> */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    /** @return BelongsTo<BankStatement, $this> */
    public function bankStatement(): BelongsTo
    {
        return $this->belongsTo(BankStatement::class);
    }

    public function reversalState(): BankTransactionReversalState
    {
        if ($this->isReversal()) {
            return BankTransactionReversalState::Reversal;
        }

        if ($this->isReversed()) {
            return BankTransactionReversalState::Reversed;
        }

        return BankTransactionReversalState::None;
    }

    // ... remaining methods unchanged
}
```

### Rename the reversal-state enum

Rename `app/Domain/BankTransactions/BankingTransactionReversalState.php` to `BankTransactionReversalState.php`.

```php
<?php

declare(strict_types=1);

namespace App\Domain\BankTransactions;

enum BankTransactionReversalState
{
    case Reversed;
    case Reversal;
    case None;
}
```

### Rename the policy

Rename `app/Policies/BankingTransactionPolicy.php` to `app/Policies/BankTransactionPolicy.php`.

```php
<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\BankTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Override;

final class BankTransactionPolicy extends ResourcePolicy
{
    #[Override]
    protected static function permissionPrefix(): string
    {
        return 'bank_transactions';
    }

    #[Override]
    public function update(User $user, Model $record): bool
    {
        if ($record instanceof BankTransaction && $record->isCompleted()) {
            return false;
        }

        return parent::update($user, $record);
    }

    #[Override]
    public function delete(User $user, Model $record): bool
    {
        if ($record instanceof BankTransaction && $record->isCompleted()) {
            return false;
        }

        return parent::delete($user, $record);
    }
}
```

### Rename the model factory

Rename `database/factories/BankingTransactionFactory.php` to `database/factories/BankTransactionFactory.php`.

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\BankTransactions\BankTransactionStatus;
use App\Models\BankAccount;
use App\Models\BankStatement;
use App\Models\BankTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/** @extends Factory<BankTransaction> */
final class BankTransactionFactory extends Factory
{
    #[Override]
    public function definition(): array
    {
        $amount = fake()->randomFloat(3, -5000, 5000);

        return [
            'date' => fake()->dateTimeBetween('-1 year', 'now'),
            'amount' => $amount,
            'description' => fake()->sentence(),
            'banking_account_number' => 'NL' . fake()->randomNumber(8, true) . fake()->randomNumber(8, true),
            'import_hash' => fake()->sha256(),
            'status' => BankTransactionStatus::Open->value,
        ];
    }

    public function forAccount(string $accountNumber): self
    {
        return $this->state(['banking_account_number' => $accountNumber]);
    }

    public function forBankAccount(BankAccount $account): self
    {
        return $this->state(['bank_account_id' => $account->id]);
    }

    public function forStatement(BankStatement $statement): self
    {
        return $this->state([
            'bank_account_id' => $statement->bank_account_id,
            'bank_statement_id' => $statement->id,
        ]);
    }

    public function completed(): self
    {
        return $this->state(['status' => BankTransactionStatus::Completed->value]);
    }

    public function reversedBy(BankTransaction $original): self
    {
        return $this->state([
            'reversed_by_transaction_id' => $original->id,
            'amount' => -$original->amount,
            'banking_account_number' => $original->banking_account_number,
        ]);
    }
}
```

### Update sibling models that reference the transaction

Update `BankAccount`, `BankStatement`, `BookkeepingRecord`, `Invoice`, and `PurchaseOrder` to import `BankTransaction` and update relation docblocks and method names where appropriate. Relation method names such as `bankingTransactions()` on `Invoice`/`PurchaseOrder` and `bankingTransaction()` on `BookkeepingRecord` should also become `bankTransactions()` and `bankTransaction()` respectively to match the new class name.

Example for `app/Models/Invoice.php`:

```php
use App\Models\BankTransaction;

/** @return MorphToMany<BankTransaction, $this> */
public function bankTransactions(): MorphToMany
{
    return $this->morphedByMany(BankTransaction::class, 'reference', 'banking_transaction_references')
        ->withTimestamps();
}
```

Example for `app/Models/BookkeepingRecord.php`:

```php
use App\Models\BankTransaction;

/** @return BelongsTo<BankTransaction, $this> */
public function bankTransaction(): BelongsTo
{
    return $this->belongsTo(BankTransaction::class);
}
```

### Update the infrastructure repository

In `app/Infrastructure/BankTransactions/BankTransactionDbRepository.php`, replace all `BankingTransaction` imports and usages with `BankTransaction`. Also rename the local variable `$bankingTransaction` to `$bankTransaction` (or `$transaction`) for consistency.

```php
use App\Models\BankTransaction;

final readonly class BankTransactionDbRepository implements BankTransactionRepository
{
    public function create(CreateBankTransaction $dto): BankTransactionId
    {
        $bankTransaction = BankTransaction::query()->create([
            'date' => $dto->date,
            'amount' => $dto->amount,
            'description' => $dto->description,
            'banking_account_number' => $dto->bankingAccountNumber,
            'bank_account_id' => $dto->bankAccountId,
            'bank_statement_id' => $dto->bankStatementId,
            'import_hash' => $dto->importHash,
        ]);

        return BankTransactionId::create($bankTransaction->id);
    }

    // ... remaining methods use BankTransaction::query()
}
```

Do the same in `app/Infrastructure/BankAccounts/BankAccountBalanceDbRepository.php`.

### Rename the job and Artisan command

Rename `app/Domain/Jobs/MatchBankingTransactionsJob.php` to `app/Domain/Jobs/MatchBankTransactionsJob.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Jobs;

use App\Domain\BankTransactions\BankTransactionRepository;
use App\Domain\BankTransactions\BankTransactionService;

final class MatchBankTransactionsJob extends BaseJob
{
    public function __construct(
        public int $batchSize = 50,
    ) {}

    public function handle(
        BankTransactionRepository $bankTransactionRepository,
        BankTransactionService $bankTransactionService,
    ): void {
        $unresolvedIds = $bankTransactionRepository->getUnresolvedIds($this->batchSize);

        if (count($unresolvedIds->ids) === 0) {
            return;
        }

        $bankTransactionService->resolveMatching($unresolvedIds);
    }
}
```

Rename `app/Console/Commands/MatchBankingTransactionsCommand.php` to `app/Console/Commands/MatchBankTransactionsCommand.php`:

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Jobs\MatchBankTransactionsJob;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:match-bank-transactions {--batch-size=50}')]
#[Description('Dispatch batch job to match unresolved bank transactions')]
final class MatchBankTransactionsCommand extends Command
{
    public function handle(): void
    {
        MatchBankTransactionsJob::dispatch(
            batchSize: (int) $this->option('batch-size'),
        );

        $this->info('MatchBankTransactionsJob dispatched.');
    }
}
```

Update `routes/console.php`:

```php
use App\Console\Commands\GenerateInvoiceBatchCommand;
use App\Console\Commands\MatchBankTransactionsCommand;

Schedule::command(MatchBankTransactionsCommand::class)->hourly();
```

Update `app/Filament/Admin/Actions/ImportMt940Action.php` to dispatch `MatchBankTransactionsJob`.

### Rename the Filament resource tree

Move the directory `app/Filament/Admin/Resources/BankingTransactions/` to `app/Filament/Admin/Resources/BankTransactions/` and rename every class inside. The namespace changes from `App\Filament\Admin\Resources\BankingTransactions\...` to `App\Filament\Admin\Resources\BankTransactions\...`.

Example `BankTransactionResource.php`:

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankTransactions;

use App\Filament\Admin\Clusters\Bookkeeping\BookkeepingCluster;
use App\Filament\Admin\Navigation\NavigationGroup;
use App\Filament\Admin\Resources\BankTransactions\Pages\CreateBankTransaction;
use App\Filament\Admin\Resources\BankTransactions\Pages\EditBankTransaction;
use App\Filament\Admin\Resources\BankTransactions\Pages\ListBankTransactions;
use App\Filament\Admin\Resources\BankTransactions\Pages\ViewBankTransaction;
use App\Filament\Admin\Resources\BankTransactions\Schemas\BankTransactionForm;
use App\Filament\Admin\Resources\BankTransactions\Tables\BankTransactionsTable;
use App\Models\BankTransaction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Override;
use UnitEnum;

final class BankTransactionResource extends Resource
{
    #[Override]
    protected static ?string $model = BankTransaction::class;

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::Banknotes;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Bookkeeping;

    #[Override]
    protected static ?string $cluster = BookkeepingCluster::class;

    #[Override]
    protected static ?int $navigationSort = 3;

    #[Override]
    protected static ?string $recordTitleAttribute = 'description';

    #[Override]
    public static function form(Schema $schema): Schema
    {
        return BankTransactionForm::configure($schema);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return BankTransactionsTable::configure($table);
    }

    #[Override]
    public static function getPluralLabel(): string
    {
        return __('labels.bank_transactions');
    }

    #[Override]
    public static function getLabel(): string
    {
        return __('labels.bank_transaction');
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ListBankTransactions::route('/'),
            'create' => CreateBankTransaction::route('/create'),
            'edit' => EditBankTransaction::route('/{record}/edit'),
            'view' => ViewBankTransaction::route('/{record}'),
        ];
    }
}
```

Apply analogous renames to every file under the old `BankingTransactions` tree:

- `Actions/AttachBookkeepingRecordAction.php`
- `Actions/AttachInvoiceAction.php`
- `Actions/AttachPurchaseOrderAction.php`
- `Actions/CompleteBankingTransactionAction.php` → `Actions/CompleteBankTransactionAction.php`
- `Actions/CreateBookkeepingRecordFromTransactionAction.php`
- `Actions/CreateInvoiceFromTransactionAction.php`
- `Actions/CreatePurchaseOrderFromTransactionAction.php`
- `Actions/LinkReversalAction.php`
- `Actions/RetryMatchingAction.php`
- `Actions/UnlinkReversalAction.php`
- `Helpers/GetTransaction.php`
- `Helpers/IsOpen.php`
- `Pages/CreateBankingTransaction.php` → `Pages/CreateBankTransaction.php`
- `Pages/EditBankingTransaction.php` → `Pages/EditBankTransaction.php`
- `Pages/ListBankingTransactions.php` → `Pages/ListBankTransactions.php`
- `Pages/ViewBankingTransaction.php` → `Pages/ViewBankTransaction.php`
- `RelationManagers/BookkeepingRecordsRelationManager.php`
- `RelationManagers/InvoicesRelationManager.php`
- `RelationManagers/PurchaseOrdersRelationManager.php`
- `Schemas/BankingTransactionForm.php` → `Schemas/BankTransactionForm.php`
- `Tables/BankingTransactionsTable.php` → `Tables/BankTransactionsTable.php`
- `Tables/BookYearFilter.php`
- `Tables/IsReversalFilter.php`
- `Tables/MonthGroup.php`
- `Tables/RunningTotalSummery.php`
- `Widgets/BankingTransactionStats.php` → `Widgets/BankTransactionStats.php`

### Update cross-resource references

Resources that import actions or relation managers from the transaction resource must update their `use` statements. Examples:

- `app/Filament/Admin/Resources/BankStatements/RelationManagers/BankStatementTransactionsRelationManager.php`
- `app/Filament/Admin/Resources/BookkeepingRecords/Schemas/BookkeepingRecordInfolist.php`
- `app/Filament/Admin/Resources/Invoices/Pages/EditInvoice.php`
- `app/Filament/Admin/Resources/Invoices/Pages/ViewInvoice.php`
- `app/Filament/Admin/Resources/Invoices/RelationManagers/InvoiceBankingTransactionsRelationManager.php` → `InvoiceBankTransactionsRelationManager.php`
- `app/Filament/Admin/Resources/PurchaseOrders/Pages/EditPurchaseOrder.php`
- `app/Filament/Admin/Resources/PurchaseOrders/Pages/ViewPurchaseOrder.php`
- `app/Filament/Admin/Resources/PurchaseOrders/RelationManagers/PurchaseOrderBankingTransactionsRelationManager.php` → `PurchaseOrderBankTransactionsRelationManager.php`

### Update authorization permissions

In `app/Domain/Authorization/ResourcePermission.php`, rename the Banking Transactions cases to use `BankTransactions` and the string prefix `bank_transactions`:

```php
// Bank Transactions
case ViewAnyBankTransactions = 'view_any_bank_transactions';
case ViewBankTransactions = 'view_bank_transactions';
case CreateBankTransactions = 'create_bank_transactions';
case UpdateBankTransactions = 'update_bank_transactions';
case DeleteBankTransactions = 'delete_bank_transactions';
case DeleteAnyBankTransactions = 'delete_any_bank_transactions';
```

In `database/seeders/RolePermissionSeeder.php`, change:

```php
$this->allPermissionsFor('bank_transactions'),
```

Existing seeded permissions in production/staging databases will need a migration or manual update so the new strings match roles; include a data migration if the environment has live roles.

### Update translations

In `lang/nl/labels.php`, change:

```php
'bank_transaction' => 'Banktransactie',
'bank_transactions' => 'Banktransacties',
'bank_transaction_information' => 'Banktransactie informatie',
```

### Rename and update tests

Rename the test files and update their namespaces and imports:

- `tests/Feature/Filament/BankingTransaction/BankingTransactionResourceTest.php` → `tests/Feature/Filament/BankTransaction/BankTransactionResourceTest.php`
- `tests/Feature/Models/BankingTransactionTest.php` → `tests/Feature/Models/BankTransactionTest.php`
- `tests/Unit/Domain/Jobs/MatchBankingTransactionsJobTest.php` → `tests/Unit/Domain/Jobs/MatchBankTransactionsJobTest.php`
- `tests/Feature/Infrastructure/BankTransactions/BankTransactionDbRepositoryTest.php` (keep path, update contents)

Example updated model test:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Domain\BankTransactions\BankTransactionStatus;
use App\Models\BankTransaction;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Member;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use Tests\FeatureTestCase;

final class BankTransactionTest extends FeatureTestCase
{
    public function test_unmatched_amount_returns_full_amount_when_no_references(): void
    {
        $transaction = BankTransaction::factory()
            ->createQuietly(['amount' => 150.500]);

        $result = $transaction->unmatched_amount;

        static::assertSame(150.5, $result);
    }

    // ... remaining tests updated to use BankTransaction::factory()
}
```

### Update domain documentation

In `.agents/docs/banking/CONTEXT.md` and `.agents/docs/GLOSSARY.md`, replace `BankingTransaction` with `BankTransaction` and update the glossary description. Also update `BankingTransactionReversalState` to `BankTransactionReversalState` in the reversal paragraph. Do not rename the bounded context itself; it remains "Banking".

### Regenerate IDE helpers and clear caches

The Laravel Idea helper files under `vendor/_laravel_idea/` contain generated references to `BankingTransaction`. Do not edit them by hand; regenerate them through the IDE or `php artisan` command after the rename.

Clear cached views and route caches:

```bash
php artisan view:clear
php artisan route:clear
php artisan cache:clear
```

## Verification

After all renames and import updates:

- Run static analysis:
  ```bash
  ./Taskfile composer run larastan
  ```
- Run the affected feature and unit tests:
  ```bash
  ./Taskfile artisan test --compact tests/Feature/Models/BankTransactionTest.php
  ./Taskfile artisan test --compact tests/Feature/Filament/BankTransaction/BankTransactionResourceTest.php
  ./Taskfile artisan test --compact tests/Feature/Infrastructure/BankTransactions/BankTransactionDbRepositoryTest.php
  ./Taskfile artisan test --compact tests/Unit/Domain/Jobs/MatchBankTransactionsJobTest.php
  ```
- Confirm routes are registered:
  ```bash
  ./Taskfile artisan route:list --name=bank
  ```

## Explicitly out of scope

The following must not be renamed because they are unrelated or external:

- `Kingsquare\Banking\*` and `Kingsquare\Parser\Banking\Mt940` (external package).
- Member registration SEPA payment attributes: `PaymentInfoData::bankingAccount`, `bankingBic`, `bankingAccountHolderName`, and related request rules and Blade views.
- The bounded context name "Banking" in `.agents/docs/`.
- Existing migration file names and database table/column names (`banking_transactions`, `banking_transaction_references`, `banking_transaction_links`, `banking_account_number`). These are kept stable by the explicit `$table` property on the model.

## Optional follow-up

If the team later wants full consistency at the database level, a separate migration can rename tables and columns from `banking_*` to `bank_*`. That is intentionally not part of this plan because it affects foreign-key constraints, pivot tables, and existing data.
