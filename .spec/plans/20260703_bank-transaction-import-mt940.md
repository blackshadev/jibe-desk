# Implementation Plan: Bank Transaction Import (MT940) & Reconciliation

## Goal

Add a new feature to import bank transactions via MT940 format (using `kingsquare/php-mt940`) and match them to the bookkeeping system. Each bank transaction (called a "BankingTransaction") can be linked to one or more Invoices, PurchaseOrders, and BookkeepingRecords. When linked to an Invoice or PurchaseOrder, the system automatically also links to that entity's BookkeepingRecords.

---

## Context & Key Findings

### Existing Architecture

| Aspect | Current State |
|--------|---------------|
| `Invoice` model | `app/Models/Invoice.php` — `status`, `date`, `invoice_number`, recipient fields, `member_id`, `invoice_batch_id`. HasMany `InvoiceLine`. `total` accessor returns `CompoundPrice`. |
| `PurchaseOrder` model | `app/Models/PurchaseOrder.php` — `description`, `date`, `status`, `creditor_name`, `creditor_iban`, `image_path`. HasMany `PurchaseOrderLine`. `total` accessor returns `CompoundPrice`. |
| `BookkeepingRecord` model | `app/Models/BookkeepingRecord.php` — `year`, `cost_center_id`, `amount_price`, `amount_vat`, `description`, polymorphic `reference` (`morphTo`). `amount` accessor maps to/from `CompoundPrice`. |
| `BookkeepingRecord::reference()` | Polymorphic `morphTo` — currently references `Invoice` or `PurchaseOrder`. |
| `BookkeepingRecordRepository` interface | `#[Autowire]` interface at `app/Domain/Bookkeeping/BookkeepingRecordRepository.php` — `createForBatch()`, `createForPurchaseOrder()`, `getResultsForYear()`. |
| `BookkeepingRecordDbRepository` | `app/Infrastructure/Bookkeeping/BookkeepingRecordDbRepository.php` — uses `insertUsing` + `whereNotExists` for idempotent bookkeeping creation. |
| Domain/Infrastructure split | Interfaces in `app/Domain/<Context>/`, implementations in `app/Infrastructure/<Context>/`. Auto-wired via `#[Autowire]`. |
| SEPA config | `config/sepa.php` — `creditor_id`, `creditor_name`, `creditor_iban`, `creditor_bic`. |
| Service pattern | Domain interfaces + `Impl` classes, `final readonly` with constructor-injected dependencies. |
| Authorization | `ResourcePolicy` base class + Spatie permissions. `ResourcePermission` enum contains all permissions. Seeded via `RolePermissionSeeder`. |
| Filament resources | `Resources/<Name>/<Name>Resource.php` + `Pages/` (Create, Edit, List, View) + `Schemas/<Name>Form.php` + `Tables/<Name>sTable.php`. |
| Navigation groups | `NavigationGroup` enum: `MemberAdministration`, `Invoicing`, `Bookkeeping`, `Rental`, `Activities`, `Technical`. |
| Console commands | `app/Console/Commands/` — use `#[Signature]` and `#[Description]` attributes. |
| Factories | `database/factories/` — `final class` extending `Factory`, `#[Override]` on `definition()`, custom state methods. |
| Tests — unit | `UnitTestCase` + Mockery expectation classes in `tests/Unit/Domain/<Context>/`. |
| Tests — feature | `FeatureTestCase` (LazilyRefreshDatabase) + `WithAuthorizedUser` trait. |
| Labels | `lang/nl/labels.php` — all Dutch UI labels. Only `nl` locale exists. |

### MT940 Package (`kingsquare/php-mt940`)

- **Version**: 2.0.0 (requires PHP >= 7)
- **Parsing**: `$parser = new \Kingsquare\Parser\Banking\Mt940(); $statements = $parser->parse(file_get_contents($file));`
- **Output**: Array of `Kingsquare\Banking\Statement` objects
- **Statement properties**: `getBank()`, `getAccount()` (IBAN/account number), `getStartPrice()`, `getEndPrice()`, `getStartTimestamp()`, `getEndTimestamp()`, `getNumber()`, `getCurrency()`, `getTransactions()` (array of Transaction)
- **Transaction properties**: `getAccount()` (counterparty account), `getAccountName()`, `getPrice()` (absolute), `getDebitCredit()` (`D` or `C`), `getDescription()`, `getValueTimestamp()`, `getEntryTimestamp()`, `getTransactionCode()`, `isDebit()`, `isCredit()`, `getRelativePrice()` (negative for debits)
- **Supported banks**: ABNAMRO, ING, KNAB, RABOBANK, SPARKASSE, TRIODOS, HSBC, SNS, BUNQ, PENTA, ASN, KBS, ZETB, KONTIST + Unknown fallback
- **Custom engines**: Can be passed to `parse()` method if needed

---

## Design Decisions

### 1. Model Name: `BankingTransaction`

The model represents a single bank transaction parsed from an MT940 file. It stores only the essential fields: `date`, `amount`, `description`, and `banking_account_number`. The MT940 parser extracts these from the transaction's value timestamp, relative price, normalized description, and the statement's account number respectively.

### 2. Split Relationships: `MorphToMany` for Invoice/PO, `HasMany` for BookkeepingRecord

**Invoice & PurchaseOrder matching** uses a polymorphic pivot table `banking_transaction_references` (`banking_transaction_id` + `reference_type` + `reference_id`) with `MorphToMany` / `morphedByMany` relationships. An Invoice or PurchaseOrder can be linked to multiple BankingTransactions (and vice versa).

**BookkeepingRecord matching** uses a direct foreign key: a new nullable `banking_transaction_id` column on the `bookkeeping_records` table. This is a `HasMany` / `BelongsTo` relationship — a BookkeepingRecord belongs to at most one BankingTransaction. This is the semantically correct model: a specific bookkeeping entry either matches a bank transaction or it doesn't.

### 3. Auto-Linking BookkeepingRecords When Attaching Invoices/PurchaseOrders

When a `BankingTransaction` is attached to an `Invoice` or `PurchaseOrder`, the system automatically sets `banking_transaction_id` on all `BookkeepingRecord` records that reference that Invoice/PurchaseOrder (via their existing polymorphic `reference` column). When detached, the FK is cleared. This is handled in a domain service layer with DB transactions.

### 4. MT940 Import via Filament Upload

The MT940 import is triggered by a **Filament page action** on the BankingTransactions list page. A modal with a file upload field accepts the `.mta` / `.mt940` file. On submit, the file is parsed synchronously and `BankingTransaction` records are created. MT940 files are typically small (< 1 MB), so synchronous processing is appropriate. A success notification shows the number of imported and skipped (duplicate) records.

### 5. Hash-Based Deduplication

A SHA-256 hash of `(value_timestamp + absolute_price + normalized_description + account_number)` is stored in the `import_hash` column (with a unique index). The description is normalized (trim whitespace, collapse multiple spaces) before hashing to handle minor formatting differences between exports.

### 6. Navigation Group: `Bookkeeping`

Banking transactions are a bookkeeping/financial concern. Placed under the `Bookkeeping` navigation group alongside Cost Centers, Bookkeeping Records, and Purchase Orders.

### 7. Permissions: `FinancialAdministration` Role

Full CRUD access to `banking_transactions` (view, create, update, delete) is granted to the `FinancialAdministration` role, matching the pattern for other bookkeeping resources.

### 8. Inverse Relationships on Invoice/PurchaseOrder/BookkeepingRecord

- `Invoice` and `PurchaseOrder` get a `bankingTransactions()` `MorphToMany` (via the pivot table)
- `BookkeepingRecord` gets a `bankingTransaction()` `BelongsTo` (via the FK)
- No changes to their Filament forms or tables — reconciliation lives entirely on the `BankingTransaction` side

---

## Phase 1: Composer Dependency

### Step 1.1 — Add `kingsquare/php-mt940`

```bash
./Taskfile composer require kingsquare/php-mt940 --no-interaction
```

No additional configuration files needed — the package works out of the box.

---

## Phase 2: Database Migration

### Step 2.1 — Create `banking_transactions` table

```bash
./Taskfile artisan make:migration create_banking_transactions_table --no-interaction
```

File: `database/migrations/YYYY_MM_DD_HHMMSS_create_banking_transactions_table.php`

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create('banking_transactions', static function (Blueprint $table): void {
            $table->id();
            $table->date('date');
            $table->decimal('amount', 10, 3);
            $table->string('description');
            $table->string('banking_account_number');
            $table->string('import_hash', 64)->unique();
            $table->timestamps();

            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banking_transactions');
    }
};
```

**Field mapping from MT940 to BankingTransaction:**

| MT940 Source | BankingTransaction Column |
|---|---|
| Transaction `getValueTimestamp()` (formatted as `Y-m-d`) | `date` |
| Transaction `getRelativePrice()` (negative for debits) | `amount` |
| Transaction `getDescription()` (normalized — trim, collapse whitespace) | `description` |
| Statement `getAccount()` | `banking_account_number` |
| Computed: `sha256(value_date \| price \| normalized_desc \| account)` | `import_hash` |

### Step 2.2 — Create `banking_transaction_references` pivot table (Invoice & PurchaseOrder only)

```bash
./Taskfile artisan make:migration create_banking_transaction_references_table --no-interaction
```

File: `database/migrations/YYYY_MM_DD_HHMMSS_create_banking_transaction_references_table.php`

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::create('banking_transaction_references', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('banking_transaction_id')->constrained('banking_transactions')->cascadeOnDelete();
            $table->nullableMorphs('reference'); // Invoice or PurchaseOrder
            $table->timestamps();

            $table->unique(
                ['banking_transaction_id', 'reference_type', 'reference_id'],
                'btr_unique',
            );
            $table->index('reference_type');
            $table->index('reference_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banking_transaction_references');
    }
};
```

### Step 2.3 — Add `banking_transaction_id` to `bookkeeping_records` table

```bash
./Taskfile artisan make:migration add_banking_transaction_id_to_bookkeeping_records_table --no-interaction
```

File: `database/migrations/YYYY_MM_DD_HHMMSS_add_banking_transaction_id_to_bookkeeping_records_table.php`

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::table('bookkeeping_records', static function (Blueprint $table): void {
            $table->foreignId('banking_transaction_id')
                ->nullable()
                ->constrained('banking_transactions')
                ->nullOnDelete();
            $table->index('banking_transaction_id');
        });
    }

    public function down(): void
    {
        Schema::table('bookkeeping_records', static function (Blueprint $table): void {
            $table->dropForeign(['banking_transaction_id']);
            $table->dropColumn('banking_transaction_id');
        });
    }
};
```

---

## Phase 3: Domain Layer

### Step 3.1 — Create `BankTransactionId` value object

File: `app/Domain/BankTransactions/BankTransactionId.php`

```php
<?php

declare(strict_types=1);

namespace App\Domain\BankTransactions;

use App\Domain\NumericId;

final readonly class BankTransactionId extends NumericId {}
```

This follows the existing pattern: `InvoiceId`, `PurchaseOrderId`, `CostCenterId` all extend `NumericId`.

### Step 3.2 — Create `CreateBankTransaction` DTO

File: `app/Domain/BankTransactions/CreateBankTransaction.php`

```php
<?php

declare(strict_types=1);

namespace App\Domain\BankTransactions;

final readonly class CreateBankTransaction
{
    public function __construct(
        public string $date,
        public float $amount,
        public string $description,
        public string $bankingAccountNumber,
        public string $importHash,
    ) {}
}
```

### Step 3.3 — Create `BankTransactionRepository` interface

File: `app/Domain/BankTransactions/BankTransactionRepository.php`

Purpose: Handles persistence of BankingTransactions — creation, attach/detach to invoices/purchase orders, and linking to BookkeepingRecords.

```php
<?php

declare(strict_types=1);

namespace App\Domain\BankTransactions;

use App\Domain\Invoices\InvoiceId;
use App\Domain\PurchaseOrders\PurchaseOrderId;
use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface BankTransactionRepository
{
    /**
     * Create a new banking transaction from the DTO. Throws if a transaction with
     * the same import_hash already exists.
     */
    public function create(CreateBankTransaction $dto): BankTransactionId;

    /**
     * Check whether a banking transaction with the given import hash already exists.
     */
    public function existsByHash(string $hash): bool;

    /**
     * Attach a banking transaction to an invoice via the pivot.
     * Also sets banking_transaction_id on all BookkeepingRecords referencing the invoice.
     */
    public function attachInvoice(BankTransactionId $bankTransactionId, InvoiceId $invoiceId): void;

    /**
     * Detach a banking transaction from an invoice.
     * Also clears banking_transaction_id on the auto-linked BookkeepingRecords.
     */
    public function detachInvoice(BankTransactionId $bankTransactionId, InvoiceId $invoiceId): void;

    /**
     * Attach a banking transaction to a purchase order via the pivot.
     * Also sets banking_transaction_id on all BookkeepingRecords referencing the purchase order.
     */
    public function attachPurchaseOrder(BankTransactionId $bankTransactionId, PurchaseOrderId $purchaseOrderId): void;

    /**
     * Detach a banking transaction from a purchase order.
     * Also clears banking_transaction_id on the auto-linked BookkeepingRecords.
     */
    public function detachPurchaseOrder(BankTransactionId $bankTransactionId, PurchaseOrderId $purchaseOrderId): void;

    /**
     * Attach a banking transaction directly to a bookkeeping record (sets FK).
     */
    public function attachBookkeepingRecord(BankTransactionId $bankTransactionId, int $bookkeepingRecordId): void;

    /**
     * Detach a banking transaction from a bookkeeping record (clears FK).
     */
    public function detachBookkeepingRecord(BankTransactionId $bankTransactionId, int $bookkeepingRecordId): void;
}
```

### Step 3.4 — Create `BankTransactionDbRepository` implementation

File: `app/Infrastructure/BankTransactions/BankTransactionDbRepository.php`

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\BankTransactions;

use App\Domain\BankTransactions\BankTransactionId;
use App\Domain\BankTransactions\BankTransactionRepository;
use App\Domain\BankTransactions\CreateBankTransaction;
use App\Domain\Invoices\InvoiceId;
use App\Domain\PurchaseOrders\PurchaseOrderId;
use App\Models\BankingTransaction;
use App\Models\BookkeepingRecord;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;
use Override;

final readonly class BankTransactionDbRepository implements BankTransactionRepository
{
    #[Override]
    public function create(CreateBankTransaction $dto): BankTransactionId
    {
        $bankingTransaction = BankingTransaction::query()->create([
            'date' => $dto->date,
            'amount' => $dto->amount,
            'description' => $dto->description,
            'banking_account_number' => $dto->bankingAccountNumber,
            'import_hash' => $dto->importHash,
        ]);

        return BankTransactionId::create($bankingTransaction->id);
    }

    #[Override]
    public function existsByHash(string $hash): bool
    {
        return BankingTransaction::query()->where('import_hash', $hash)->exists();
    }

    #[Override]
    public function attachInvoice(BankTransactionId $bankTransactionId, InvoiceId $invoiceId): void
    {
        DB::transaction(function () use ($bankTransactionId, $invoiceId): void {
            /** @var BankingTransaction $bankingTransaction */
            $bankingTransaction = BankingTransaction::query()->findOrFail($bankTransactionId->value);
            $bankingTransaction->invoices()->syncWithoutDetaching([$invoiceId->value]);

            // Set banking_transaction_id on BookkeepingRecords that reference this invoice
            BookkeepingRecord::query()
                ->where('reference_type', Invoice::class)
                ->where('reference_id', $invoiceId->value)
                ->update(['banking_transaction_id' => $bankTransactionId->value]);
        });
    }

    #[Override]
    public function detachInvoice(BankTransactionId $bankTransactionId, InvoiceId $invoiceId): void
    {
        DB::transaction(function () use ($bankTransactionId, $invoiceId): void {
            /** @var BankingTransaction $bankingTransaction */
            $bankingTransaction = BankingTransaction::query()->findOrFail($bankTransactionId->value);
            $bankingTransaction->invoices()->detach($invoiceId->value);

            // Clear banking_transaction_id on BookkeepingRecords for this invoice
            BookkeepingRecord::query()
                ->where('reference_type', Invoice::class)
                ->where('reference_id', $invoiceId->value)
                ->where('banking_transaction_id', $bankTransactionId->value)
                ->update(['banking_transaction_id' => null]);
        });
    }

    #[Override]
    public function attachPurchaseOrder(BankTransactionId $bankTransactionId, PurchaseOrderId $purchaseOrderId): void
    {
        DB::transaction(function () use ($bankTransactionId, $purchaseOrderId): void {
            /** @var BankingTransaction $bankingTransaction */
            $bankingTransaction = BankingTransaction::query()->findOrFail($bankTransactionId->value);
            $bankingTransaction->purchaseOrders()->syncWithoutDetaching([$purchaseOrderId->value]);

            BookkeepingRecord::query()
                ->where('reference_type', PurchaseOrder::class)
                ->where('reference_id', $purchaseOrderId->value)
                ->update(['banking_transaction_id' => $bankTransactionId->value]);
        });
    }

    #[Override]
    public function detachPurchaseOrder(BankTransactionId $bankTransactionId, PurchaseOrderId $purchaseOrderId): void
    {
        DB::transaction(function () use ($bankTransactionId, $purchaseOrderId): void {
            /** @var BankingTransaction $bankingTransaction */
            $bankingTransaction = BankingTransaction::query()->findOrFail($bankTransactionId->value);
            $bankingTransaction->purchaseOrders()->detach($purchaseOrderId->value);

            BookkeepingRecord::query()
                ->where('reference_type', PurchaseOrder::class)
                ->where('reference_id', $purchaseOrderId->value)
                ->where('banking_transaction_id', $bankTransactionId->value)
                ->update(['banking_transaction_id' => null]);
        });
    }

    #[Override]
    public function attachBookkeepingRecord(BankTransactionId $bankTransactionId, int $bookkeepingRecordId): void
    {
        BookkeepingRecord::query()
            ->where('id', $bookkeepingRecordId)
            ->update(['banking_transaction_id' => $bankTransactionId->value]);
    }

    #[Override]
    public function detachBookkeepingRecord(BankTransactionId $bankTransactionId, int $bookkeepingRecordId): void
    {
        BookkeepingRecord::query()
            ->where('id', $bookkeepingRecordId)
            ->where('banking_transaction_id', $bankTransactionId->value)
            ->update(['banking_transaction_id' => null]);
    }
}
```

---

## Phase 4: Eloquent Models

### Step 4.1 — Create `BankingTransaction` model

File: `app/Models/BankingTransaction.php`

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Override;

/**
 * @property string $description
 */
#[Guarded(['id', 'created_at', 'updated_at'])]
final class BankingTransaction extends Model
{
    use HasFactory;

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

    #[Override]
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'amount' => 'decimal:3',
        ];
    }
}
```

### Step 4.2 — Add `bankingTransactions()` relationship to `Invoice` model

Add to `app/Models/Invoice.php`:

```php
use App\Models\BankingTransaction;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/** @return MorphToMany<BankingTransaction, $this> */
public function bankingTransactions(): MorphToMany
{
    return $this->morphToMany(BankingTransaction::class, 'reference', 'banking_transaction_references')
        ->withTimestamps();
}
```

### Step 4.3 — Add `bankingTransactions()` relationship to `PurchaseOrder` model

Add to `app/Models/PurchaseOrder.php`:

```php
use App\Models\BankingTransaction;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/** @return MorphToMany<BankingTransaction, $this> */
public function bankingTransactions(): MorphToMany
{
    return $this->morphToMany(BankingTransaction::class, 'reference', 'banking_transaction_references')
        ->withTimestamps();
}
```

### Step 4.4 — Add `bankingTransaction()` relationship to `BookkeepingRecord` model

Add to `app/Models/BookkeepingRecord.php`:

```php
use App\Models\BankingTransaction;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @return BelongsTo<BankingTransaction, $this> */
public function bankingTransaction(): BelongsTo
{
    return $this->belongsTo(BankingTransaction::class);
}
```

---

## Phase 5: Factory & Seeder (Optional)

### Step 5.1 — Create `BankingTransactionFactory`

File: `database/factories/BankingTransactionFactory.php`

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\BankingTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/** @extends Factory<BankingTransaction> */
final class BankingTransactionFactory extends Factory
{
    #[Override]
    public function definition(): array
    {
        $amount = fake()->randomFloat(2, -5000, 5000);

        return [
            'date' => fake()->dateTimeBetween('-1 year', 'now'),
            'amount' => $amount,
            'description' => fake()->sentence(),
            'banking_account_number' => 'NL' . fake()->randomNumber(8, true) . fake()->randomNumber(8, true),
            'import_hash' => fake()->sha256(),
        ];
    }

    public function forAccount(string $accountNumber): self
    {
        return $this->state(['banking_account_number' => $accountNumber]);
    }
}
```

---

## Phase 6: MT940 Import via Filament

### Step 6.1 — Create `BankTransactionImportService` interface

File: `app/Domain/BankTransactions/BankTransactionImportService.php`

```php
<?php

declare(strict_types=1);

namespace App\Domain\BankTransactions;

use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface BankTransactionImportService
{
    /**
     * Import bank transactions from an MT940 file.
     *
     * @return array{imported: int, skipped: int} Count of newly imported and skipped (duplicate) records.
     */
    public function importFromFile(string $filePath): array;
}
```

### Step 6.2 — Create `BankTransactionImportServiceImpl`

File: `app/Infrastructure/BankTransactions/BankTransactionImportServiceImpl.php`

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\BankTransactions;

use App\Domain\BankTransactions\BankTransactionImportService;
use App\Domain\BankTransactions\BankTransactionRepository;
use App\Domain\BankTransactions\CreateBankTransaction;
use Kingsquare\Banking\Statement;
use Kingsquare\Banking\Transaction;
use Kingsquare\Parser\Banking\Mt940;
use Override;

final readonly class BankTransactionImportServiceImpl implements BankTransactionImportService
{
    public function __construct(
        private BankTransactionRepository $repository,
    ) {}

    #[Override]
    public function importFromFile(string $filePath): array
    {
        if (! file_exists($filePath)) {
            throw new \InvalidArgumentException("File not found: {$filePath}");
        }

        $content = file_get_contents($filePath);
        $parser = new Mt940();
        $statements = $parser->parse($content);

        $imported = 0;
        $skipped = 0;

        foreach ($statements as $statement) {
            foreach ($statement->getTransactions() as $transaction) {
                $hash = $this->computeHash($transaction, $statement);

                if ($this->repository->existsByHash($hash)) {
                    $skipped++;
                    continue;
                }

                $this->repository->create(new CreateBankTransaction(
                    date: $this->formatDate($transaction->getValueTimestamp('Y-m-d')) ?? '',
                    amount: $transaction->getRelativePrice(),
                    description: $this->normalizeDescription($transaction->getDescription()),
                    bankingAccountNumber: $statement->getAccount(),
                    importHash: $hash,
                ));

                $imported++;
            }
        }

        return ['imported' => $imported, 'skipped' => $skipped];
    }

    private function computeHash(Transaction $transaction, Statement $statement): string
    {
        $data = implode('|', [
            $transaction->getValueTimestamp('Y-m-d'),
            number_format($transaction->getPrice(), 2, '.', ''),
            $this->normalizeDescription($transaction->getDescription()),
            $statement->getAccount(),
        ]);

        return hash('sha256', $data);
    }

    private function normalizeDescription(string $description): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $description));
    }

    private function formatDate(string $date): ?string
    {
        if (empty($date) || $date === '1970-01-01') {
            return null;
        }

        return $date;
    }
}
```

### Step 6.3 — Create Import action on the List page

Replace the List page to include an "Import MT940" header action:

File: `app/Filament/Admin/Resources/BankingTransactions/Pages/ListBankingTransactions.php`

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankingTransactions\Pages;

use App\Domain\BankTransactions\BankTransactionImportService;
use App\Filament\Admin\Resources\BankingTransactions\BankingTransactionResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListBankingTransactions extends ListRecords
{
    protected static string $resource = BankingTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importMt940')
                ->label(__('labels.import_mt940'))
                ->modalHeading(__('labels.import_mt940'))
                ->form([
                    FileUpload::make('mt940_file')
                        ->label(__('labels.mt940_file'))
                        ->acceptedFileTypes(['text/plain', 'application/octet-stream'])
                        ->directory('mt940-imports')
                        ->disk('local')
                        ->required(),
                ])
                ->action(function (array $data, BankTransactionImportService $importService): void {
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

                    $this->refreshTable();
                }),
            CreateAction::make(),
        ];
    }
}
```

---

## Phase 7: Filament Resource

### Step 7.1 — Create `BankingTransactionResource`

File: `app/Filament/Admin/Resources/BankingTransactions/BankingTransactionResource.php`

Pattern: Follows `BookkeepingRecordResource` and `PurchaseOrderResource` with the standard structure.

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankingTransactions;

use App\Filament\Admin\Navigation\NavigationGroup;
use App\Filament\Admin\Resources\BankingTransactions\Pages\CreateBankingTransaction;
use App\Filament\Admin\Resources\BankingTransactions\Pages\EditBankingTransaction;
use App\Filament\Admin\Resources\BankingTransactions\Pages\ListBankingTransactions;
use App\Filament\Admin\Resources\BankingTransactions\Pages\ViewBankingTransaction;
use App\Filament\Admin\Resources\BankingTransactions\Schemas\BankingTransactionForm;
use App\Filament\Admin\Resources\BankingTransactions\Tables\BankingTransactionsTable;
use App\Models\BankingTransaction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Override;
use UnitEnum;

final class BankingTransactionResource extends Resource
{
    protected static ?string $model = BankingTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Banknotes;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Bookkeeping;

    protected static ?string $recordTitleAttribute = 'description';

    #[Override]
    public static function form(Schema $schema): Schema
    {
        return BankingTransactionForm::configure($schema);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return BankingTransactionsTable::configure($table);
    }

    #[Override]
    public static function getPluralLabel(): string
    {
        return __('labels.banking_transactions');
    }

    #[Override]
    public static function getLabel(): string
    {
        return __('labels.banking_transaction');
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ListBankingTransactions::route('/'),
            'create' => CreateBankingTransaction::route('/create'),
            'edit' => EditBankingTransaction::route('/{record}/edit'),
            'view' => ViewBankingTransaction::route('/{record}'),
        ];
    }
}
```

### Step 7.2 — Table Schema

File: `app/Filament/Admin/Resources/BankingTransactions/Tables/BankingTransactionsTable.php`

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankingTransactions\Tables;

use App\Filament\Admin\Resources\BankingTransactions\BankingTransactionResource;
use App\Filament\Admin\Utils\ViewOrEdit;
use App\Models\BankingTransaction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class BankingTransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('date')
                    ->label(__('labels.date'))
                    ->sortable()
                    ->date(),
                TextColumn::make('description')
                    ->label(__('labels.description'))
                    ->searchable()
                    ->limit(60),
                TextColumn::make('amount')
                    ->label(__('labels.price'))
                    ->money('EUR')
                    ->sortable()
                    ->alignEnd()
                    ->color(static fn (BankingTransaction $record): string => $record->amount < 0 ? 'danger' : 'success'),
                TextColumn::make('banking_account_number')
                    ->label(__('labels.banking_account_number'))
                    ->searchable(),
                TextColumn::make('matched_count')
                    ->label(__('labels.matched_references'))
                    ->state(static fn (BankingTransaction $record): int => $record->loadCount(['invoices', 'purchaseOrders', 'bookkeepingRecords'])
                        ->invoices_count + $record->purchase_orders_count + $record->bookkeeping_records_count),
                TextColumn::make('created_at')
                    ->label(__('labels.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('banking_account_number')
                    ->label(__('labels.banking_account_number'))
                    ->options(
                        static fn () => BankingTransaction::query()
                            ->distinct()
                            ->pluck('banking_account_number', 'banking_account_number')
                            ->toArray(),
                    ),
                Filter::make('unmatched')
                    ->label(__('labels.unmatched'))
                    ->query(static fn (Builder $query): Builder => $query
                        ->whereDoesntHave('invoices')
                        ->whereDoesntHave('purchaseOrders')
                        ->whereDoesntHave('bookkeepingRecords')),
            ])
            ->recordUrl(ViewOrEdit::route(BankingTransactionResource::class))
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
```

### Step 7.3 — Form Schema

File: `app/Filament/Admin/Resources/BankingTransactions/Schemas/BankingTransactionForm.php`

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankingTransactions\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class BankingTransactionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns()
            ->components([
                Section::make(__('labels.banking_transaction_information'))
                    ->schema([
                        DatePicker::make('date')
                            ->label(__('labels.date'))
                            ->native(false)
                            ->format('d-m-Y')
                            ->required(),
                        TextInput::make('description')
                            ->label(__('labels.description'))
                            ->columnSpanFull()
                            ->required(),
                        TextInput::make('amount')
                            ->label(__('labels.price'))
                            ->prefix('€')
                            ->numeric()
                            ->required(),
                        TextInput::make('banking_account_number')
                            ->label(__('labels.banking_account_number'))
                            ->required(),
                    ]),
            ]);
    }
}
```

### Step 7.4 — Pages

The List page is already defined in Phase 6.3 (with the import action). Create and View pages follow the standard pattern:

File: `app/Filament/Admin/Resources/BankingTransactions/Pages/CreateBankingTransaction.php`

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankingTransactions\Pages;

use App\Filament\Admin\Resources\BankingTransactions\BankingTransactionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBankingTransaction extends CreateRecord
{
    protected static string $resource = BankingTransactionResource::class;
}
```

File: `app/Filament/Admin/Resources/BankingTransactions/Pages/ViewBankingTransaction.php`

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankingTransactions\Pages;

use App\Filament\Admin\Resources\BankingTransactions\BankingTransactionResource;
use Filament\Resources\Pages\ViewRecord;

class ViewBankingTransaction extends ViewRecord
{
    protected static string $resource = BankingTransactionResource::class;

    public function getRelationManagers(): array
    {
        return [
            BankingTransactionResource\RelationManagers\InvoicesRelationManager::class,
            BankingTransactionResource\RelationManagers\PurchaseOrdersRelationManager::class,
            BankingTransactionResource\RelationManagers\BookkeepingRecordsRelationManager::class,
        ];
    }
}
```

File: `app/Filament/Admin/Resources/BankingTransactions/Pages/EditBankingTransaction.php`

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankingTransactions\Pages;

use App\Filament\Admin\Resources\BankingTransactions\Actions\AttachInvoiceAction;
use App\Filament\Admin\Resources\BankingTransactions\Actions\AttachPurchaseOrderAction;
use App\Filament\Admin\Resources\BankingTransactions\Actions\AttachBookkeepingRecordAction;
use App\Filament\Admin\Resources\BankingTransactions\BankingTransactionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Override;

final class EditBankingTransaction extends EditRecord
{
    protected static string $resource = BankingTransactionResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            AttachInvoiceAction::make(),
            AttachPurchaseOrderAction::make(),
            AttachBookkeepingRecordAction::make(),
            DeleteAction::make(),
        ];
    }

    public function getRelationManagers(): array
    {
        return [
            BankingTransactionResource\RelationManagers\InvoicesRelationManager::class,
            BankingTransactionResource\RelationManagers\PurchaseOrdersRelationManager::class,
            BankingTransactionResource\RelationManagers\BookkeepingRecordsRelationManager::class,
        ];
    }
}
```

### Step 7.5 — Custom Attach Actions

The three attach actions use `BankTransactionRepository` for the logic (already covered in Phase 3.3). The `DetachAction` in the relation managers can use Filament's default `DetachAction` for the pivot-based invoices and purchaseOrders. For the `BookkeepingRecordsRelationManager`, the detach action should use `BankTransactionRepository::detachBookkeepingRecord()` since it clears the FK.

**Note**: Since the BookkeepingRecord relationship is now a `HasMany` (not a pivot), Filament's default `DetachAction` would delete the record itself. Instead, the BookkeepingRecords relation manager should use a custom action that calls `BankTransactionDbRepository::detachBookkeepingRecord()` to set `banking_transaction_id` to null.

File: `app/Filament/Admin/Resources/BankingTransactions/RelationManagers/BookkeepingRecordsRelationManager.php`

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankingTransactions\RelationManagers;

use App\Domain\BankTransactions\BankTransactionId;
use App\Domain\BankTransactions\BankTransactionRepository;
use App\Models\BookkeepingRecord;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BookkeepingRecordsRelationManager extends RelationManager
{
    protected static string $relationship = 'bookkeepingRecords';

    protected static ?string $title = 'Boekhouding mutaties';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('year')
                    ->label(__('labels.book_year')),
                TextColumn::make('costCenter.title')
                    ->label(__('labels.cost_center')),
                TextColumn::make('description')
                    ->label(__('labels.description')),
                TextColumn::make('amount')
                    ->label(__('labels.price'))
                    ->money('EUR'),
            ])
            ->headerActions([])
            ->actions([
                Action::make('detach')
                    ->label(__('labels.detach'))
                    ->color('danger')
                    ->icon('heroicon-o-x-mark')
                    ->requiresConfirmation()
                    ->action(function (BookkeepingRecord $record, BankTransactionRepository $repository): void {
                        $repository->detachBookkeepingRecord(
                            BankTransactionId::create((int) $this->getOwnerRecord()->id),
                            $record->id,
                        );

                        Notification::make()
                            ->title(__('labels.detached'))
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
```

---

## Phase 8: Localization

### Step 8.1 — Add labels to `lang/nl/labels.php`

```php
// Banking Transactions
'banking_transaction' => 'Banktransactie',
'banking_transactions' => 'Banktransacties',
'banking_transaction_information' => 'Banktransactie informatie',
'import_mt940' => 'Importeer MT940',
'mt940_file' => 'MT940 bestand',
'import_complete' => 'Import voltooid',
'import_result' => ':imported nieuw geïmporteerd, :skipped overgeslagen (duplicaten)',
'attach_invoice' => 'Koppel factuur',
'attach_purchase_order' => 'Koppel inkooporder',
'attach_bookkeeping_record' => 'Koppel boekhouding mutatie',
'attached' => 'Gekoppeld',
'detach' => 'Ontkoppelen',
'detached' => 'Ontkoppeld',
'matched_references' => 'Gekoppelde verwijzingen',
'unmatched' => 'Niet gekoppeld',
```

---

## Phase 9: Authorization & Permissions

### Step 9.1 — Add `banking_transactions` permissions to `ResourcePermission` enum

File: `app/Domain/Authorization/ResourcePermission.php`

Add these cases to the enum (before the closing `}`):

```php
// Banking Transactions
case ViewAnyBankingTransactions = 'view_any_banking_transactions';
case ViewBankingTransactions = 'view_banking_transactions';
case CreateBankingTransactions = 'create_banking_transactions';
case UpdateBankingTransactions = 'update_banking_transactions';
case DeleteBankingTransactions = 'delete_banking_transactions';
case DeleteAnyBankingTransactions = 'delete_any_banking_transactions';
```

### Step 9.2 — Create `BankingTransactionPolicy`

File: `app/Policies/BankingTransactionPolicy.php`

```php
<?php

declare(strict_types=1);

namespace App\Policies;

final class BankingTransactionPolicy extends ResourcePolicy
{
    protected static function permissionPrefix(): string
    {
        return 'banking_transactions';
    }
}
```

### Step 9.3 — Grant permissions to `FinancialAdministration` role

File: `database/seeders/RolePermissionSeeder.php`

In `seedFinancialAdministration()`, add to the merged array:

```php
$this->allPermissionsFor('banking_transactions'),
```

---

## Phase 10: Testing

### Step 10.1 — Feature Test: `BankTransactionImportServiceImplTest`

File: `tests/Feature/Infrastructure/BankTransactions/BankTransactionImportServiceImplTest.php`

Test cases:
- Importing a valid MT940 file creates BankingTransaction records via the repository with correct fields
- Importing the same file twice creates no duplicates (hash-based deduplication via `repository->existsByHash()`)
- Importing an invalid/non-existent file throws an exception
- The returned array shows correct import/skip counts

**Test data**: Create a small MT940 fixture file in `tests/Fixtures/mt940/sample.mta`. Mock the `BankTransactionRepository` to verify delegation.

### Step 10.2 — Feature Test: `BankTransactionDbRepositoryTest`

File: `tests/Feature/Infrastructure/BankTransactions/BankTransactionDbRepositoryTest.php`

Test cases:
- `create(CreateBankTransaction)` creates a new BankingTransaction and returns a `BankTransactionId`
- `create()` with an existing `import_hash` throws an exception (unique constraint violation)
- `existsByHash()` returns `true` when a record with the hash exists
- `existsByHash()` returns `false` when no record matches
- `attachInvoice` creates pivot row and sets `banking_transaction_id` on related BookkeepingRecords
- `attachInvoice` is idempotent with existing pivot (syncWithoutDetaching)
- `detachInvoice` removes pivot row and clears `banking_transaction_id` on related BookkeepingRecords
- `attachPurchaseOrder` creates pivot row and sets FK on related BookkeepingRecords
- `detachPurchaseOrder` removes pivot row and clears FK
- `attachBookkeepingRecord` sets `banking_transaction_id` directly
- `detachBookkeepingRecord` clears `banking_transaction_id`

**Note**: These tests should extend `FeatureTestCase` with `LazilyRefreshDatabase` since the repository interacts with the database directly.

### Step 10.3 — Feature Test: `BankingTransactionResourceTest` (Filament)

File: `tests/Feature/Filament/BankingTransaction/BankingTransactionResourceTest.php`

Test cases:
- List page is accessible to users with `view_any_banking_transactions` permission
- List page is forbidden without permission
- List page shows the "Import MT940" action
- Edit page shows attach actions (Invoice, PurchaseOrder, BookkeepingRecord)
- View page shows linked references via relation managers

### Step 10.4 — Feature Test: `BankingTransactionImportTest` (Filament import action)

File: `tests/Feature/Filament/BankingTransaction/BankingTransactionImportTest.php`

Test cases:
- Uploading a valid MT940 file via the Filament action creates records
- Uploading the same file twice shows skipped count
- Uploading an invalid file shows an error

---

## Phase 11: Final Verification

### Step 11.1 — Run relevant tests

```bash
./Taskfile artisan test --compact --filter=BankTransaction
./Taskfile artisan test --compact --filter=BankingTransaction
```

### Step 11.2 — Run full test suite

```bash
./Taskfile artisan test --compact
```

### Step 11.3 — Verify Filament navigation

Confirm the `BankingTransactions` resource appears under the `Bookkeeping` navigation group with the Banknotes icon, and the "Import MT940" action is visible on the list page.

### Step 11.4 — Verify permission seeding

Run `./Taskfile artisan db:seed --class=RolePermissionSeeder` and confirm the `FinancialAdministration` role has `banking_transactions` permissions.

---

## Summary of Files to Create

| File | Purpose |
|------|---------|
| `database/migrations/*_create_banking_transactions_table.php` | BankingTransaction table |
| `database/migrations/*_create_banking_transaction_references_table.php` | Polymorphic pivot for Invoice/PO |
| `database/migrations/*_add_banking_transaction_id_to_bookkeeping_records_table.php` | FK on bookkeeping_records |
| `app/Domain/BankTransactions/BankTransactionId.php` | ID value object |
| `app/Domain/BankTransactions/CreateBankTransaction.php` | Creation DTO |
| `app/Domain/BankTransactions/BankTransactionRepository.php` | Repository interface (create + attach/detach) |
| `app/Infrastructure/BankTransactions/BankTransactionDbRepository.php` | Repository implementation |
| `app/Domain/BankTransactions/BankTransactionImportService.php` | Import service interface |
| `app/Infrastructure/BankTransactions/BankTransactionImportServiceImpl.php` | MT940 import service impl |
| `app/Models/BankingTransaction.php` | Eloquent model |
| `app/Policies/BankingTransactionPolicy.php` | Authorization policy |
| `app/Filament/Admin/Resources/BankingTransactions/BankingTransactionResource.php` | Filament resource |
| `app/Filament/Admin/Resources/BankingTransactions/Pages/ListBankingTransactions.php` | List page (with import action) |
| `app/Filament/Admin/Resources/BankingTransactions/Pages/CreateBankingTransaction.php` | Create page |
| `app/Filament/Admin/Resources/BankingTransactions/Pages/EditBankingTransaction.php` | Edit page (with attach actions) |
| `app/Filament/Admin/Resources/BankingTransactions/Pages/ViewBankingTransaction.php` | View page |
| `app/Filament/Admin/Resources/BankingTransactions/Schemas/BankingTransactionForm.php` | Form schema |
| `app/Filament/Admin/Resources/BankingTransactions/Tables/BankingTransactionsTable.php` | Table schema |
| `app/Filament/Admin/Resources/BankingTransactions/Actions/AttachInvoiceAction.php` | Attach invoice modal action |
| `app/Filament/Admin/Resources/BankingTransactions/Actions/AttachPurchaseOrderAction.php` | Attach PO modal action |
| `app/Filament/Admin/Resources/BankingTransactions/Actions/AttachBookkeepingRecordAction.php` | Attach BR modal action |
| `app/Filament/Admin/Resources/BankingTransactions/RelationManagers/InvoicesRelationManager.php` | Linked invoices |
| `app/Filament/Admin/Resources/BankingTransactions/RelationManagers/PurchaseOrdersRelationManager.php` | Linked POs |
| `app/Filament/Admin/Resources/BankingTransactions/RelationManagers/BookkeepingRecordsRelationManager.php` | Linked BRs (custom detach) |
| `database/factories/BankingTransactionFactory.php` | Model factory |
| `tests/Fixtures/mt940/sample.mta` | MT940 test fixture |
| `tests/Feature/Infrastructure/BankTransactions/BankTransactionImportServiceImplTest.php` | Import service test |
| `tests/Feature/Infrastructure/BankTransactions/BankTransactionDbRepositoryTest.php` | Repository test (create + attach/detach) |
| `tests/Feature/Filament/BankingTransaction/BankingTransactionResourceTest.php` | Filament resource test |
| `tests/Feature/Filament/BankingTransaction/BankingTransactionImportTest.php` | Filament import test |

## Summary of Files to Modify

| File | Change |
|------|--------|
| `composer.json` | Add `kingsquare/php-mt940` |
| `app/Models/Invoice.php` | Add `bankingTransactions()` MorphToMany |
| `app/Models/PurchaseOrder.php` | Add `bankingTransactions()` MorphToMany |
| `app/Models/BookkeepingRecord.php` | Add `bankingTransaction()` BelongsTo |
| `app/Domain/Authorization/ResourcePermission.php` | Add 6 banking_transactions cases |
| `database/seeders/RolePermissionSeeder.php` | Grant to FinancialAdministration |
| `lang/nl/labels.php` | Add ~14 new label entries |
