# Implementation Plan: Automated Banking Transaction Matching

**Date**: 2026-07-25
**Feature**: Automatically match banking transactions to Invoices (credits) or PurchaseOrders (debits) via a background job, based on IBAN, amount, and date proximity.
**Goal**: When banking transactions are imported or created, a background job attempts to find matching open/pending Invoices or PurchaseOrders. Matches are linked; unmatched transactions are marked as unresolvable (retryable via Filament).

---

## Overview of Current Architecture

```
BankingTransaction
  ├── status: open | completed (financial balancing state)
  ├── date, amount, description, banking_account_number, import_hash
  ├── invoices (morphToMany via banking_transaction_references)
  ├── purchaseOrders (morphToMany via banking_transaction_references)
  └── bookkeepingRecords (hasMany)

Invoice
  ├── status: open | pending | paid | declined
  ├── member → paymentInformation → banking_account_number (IBAN)
  ├── lines → total (CompoundPrice sum)
  └── scope: openOrPending(), orderByAmountProximity(float)

PurchaseOrder
  ├── status: open | pending | paid
  ├── creditor_iban (IBAN)
  ├── lines → total (CompoundPrice sum)
  └── scope: openOrPending(), orderByRelevancy(float, string) -- sorts by IBAN match + amount proximity
```

**Key insight**: The existing `AttachInvoiceAction` and `AttachPurchaseOrderAction` already provide manual matching UI with smart ordering (`orderByAmountProximity`, `orderByRelevancy`). This feature **automates** that manual process.

---

## Files to Create

### 1. New Enum: `app/Domain/BankTransactions/ResolveStatus.php`

```php
<?php

declare(strict_types=1);

namespace App\Domain\BankTransactions;

enum ResolveStatus: string
{
    case Unresolved = 'unresolved';
    case Resolved = 'resolved';
    case Unresolvable = 'unresolvable';
}
```

### 2. New DTO: `app/Domain/BankTransactions/MatchCriteria.php`

The DTO passed to `TransactionMatchingService::findMatch()`. Contains the search parameters extracted from a banking transaction.

```php
<?php

declare(strict_types=1);

namespace App\Domain\BankTransactions;

final readonly class MatchCriteria
{
    public function __construct(
        public \DateTimeInterface $date,
        public float $amount,
        public string $bankingAccountNumber,
    ) {}
}
```

### 3. New Value Object: `app/Domain/BankTransactions/BankTransactionIdList.php`

Follows the existing pattern of `InvoiceIdList` / `PurchaseOrderIdList`.

```php
<?php

declare(strict_types=1);

namespace App\Domain\BankTransactions;

use Webmozart\Assert\Assert;

final class BankTransactionIdList
{
    /** @param BankTransactionId[] $ids */
    public function __construct(
        public array $ids,
    ) {
        /** @phpstan-ignore-next-line staticMethod.alreadyNarrowedType */
        Assert::allIsInstanceOf($ids, BankTransactionId::class);
    }

    /** @param int[] $array */
    public static function fromArray(array $array): self
    {
        return new self(
            array_map(
                BankTransactionId::create(...),
                $array,
            ),
        );
    }
}
```

### 4. New DTO: `app/Domain/BankTransactions/MatchResult.php`

Replaces vague `?array` return. Three named constructors express intent clearly — no null checks, no array key access.

```php
<?php

declare(strict_types=1);

namespace App\Domain\BankTransactions;

use App\Domain\Invoices\InvoiceId;
use App\Domain\PurchaseOrders\PurchaseOrderId;

final readonly class MatchResult
{
    private function __construct(
        public bool $isMatch,
        public ?InvoiceId $invoiceId = null,
        public ?PurchaseOrderId $purchaseOrderId = null,
    ) {}

    public static function foundInvoice(InvoiceId $invoiceId): self
    {
        return new self(isMatch: true, invoiceId: $invoiceId);
    }

    public static function foundPurchaseOrder(PurchaseOrderId $purchaseOrderId): self
    {
        return new self(isMatch: true, purchaseOrderId: $purchaseOrderId);
    }

    public static function none(): self
    {
        return new self(isMatch: false);
    }
}
```

### 5. New Domain Service Interface: `app/Domain/BankTransactions/TransactionMatchingService.php`

```php
<?php

declare(strict_types=1);

namespace App\Domain\BankTransactions;

use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface TransactionMatchingService
{
    /**
     * Find a matching Invoice (credit) or PurchaseOrder (debit) for the given criteria.
     *
     * Returns MatchResult::foundInvoice(), MatchResult::foundPurchaseOrder(), or MatchResult::none().
     */
    public function findMatch(MatchCriteria $criteria): MatchResult;
}
```

### 6. Domain Service Implementation: `app/Domain/BankTransactions/TransactionMatchingServiceImpl.php`

```php
<?php

declare(strict_types=1);

namespace App\Domain\BankTransactions;

use App\Domain\Invoices\InvoiceRepository;
use App\Domain\PurchaseOrders\PurchaseOrderRepository;
use Override;

final readonly class TransactionMatchingServiceImpl implements TransactionMatchingService
{
    public function __construct(
        private InvoiceRepository $invoiceRepository,
        private PurchaseOrderRepository $purchaseOrderRepository,
    ) {}

    #[Override]
    public function findMatch(MatchCriteria $criteria): MatchResult
    {
        if ($criteria->amount > 0) {
            return $this->findMatchingInvoice($criteria);
        }

        return $this->findMatchingPurchaseOrder($criteria);
    }

    private function findMatchingInvoice(MatchCriteria $criteria): MatchResult
    {
        $invoiceId = $this->invoiceRepository->findMatchingCredit(
            bankingAccountNumber: $criteria->bankingAccountNumber,
            amount: $criteria->amount,
            date: $criteria->date,
        );

        if ($invoiceId === null) {
            return MatchResult::none();
        }

        return MatchResult::foundInvoice($invoiceId);
    }

    private function findMatchingPurchaseOrder(MatchCriteria $criteria): MatchResult
    {
        $purchaseOrderId = $this->purchaseOrderRepository->findMatchingDebit(
            creditorIban: $criteria->bankingAccountNumber,
            amount: abs($criteria->amount),
            date: $criteria->date,
        );

        if ($purchaseOrderId === null) {
            return MatchResult::none();
        }

        return MatchResult::foundPurchaseOrder($purchaseOrderId);
    }
}
```

### 7. New Background Job: `app/Domain/Jobs/MatchBankingTransactionsJob.php`

Thin batch-only job. Fetches unresolved IDs from repo, delegates all logic to `BankTransactionService::resolveMatching()`. No Eloquent queries, no conditional single-id path — the job is a pure batch orchestrator.

```php
<?php

declare(strict_types=1);

namespace App\Domain\Jobs;

use App\Domain\BankTransactions\BankTransactionRepository;
use App\Domain\BankTransactions\BankTransactionService;

final class MatchBankingTransactionsJob extends BaseJob
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

### 8. New Artisan Command: `app/Console/Commands/MatchBankingTransactionsCommand.php`

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Jobs\MatchBankingTransactionsJob;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:match-banking-transactions {--batch-size=50}')]
#[Description('Dispatch batch job to match unresolved banking transactions')]
final class MatchBankingTransactionsCommand extends Command
{
    public function handle(): void
    {
        MatchBankingTransactionsJob::dispatch(
            batchSize: (int) $this->option('batch-size'),
        );

        $this->info('MatchBankingTransactionsJob dispatched.');
    }
}
```

---

## Files to Modify

### 8. Database Migration: `database/migrations/2026_07_25_000000_add_resolve_status_to_banking_transactions_table.php`

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banking_transactions', static function (Blueprint $table): void {
            $table->string('resolve_status')->default('unresolved')->after('status');
            $table->index('resolve_status');
        });
    }

    public function down(): void
    {
        Schema::table('banking_transactions', static function (Blueprint $table): void {
            $table->dropIndex(['resolve_status']);
            $table->dropColumn('resolve_status');
        });
    }
};
```

### 9. Update Model: `app/Models/BankingTransaction.php`

**Changes:**
- Add `resolve_status` to `casts()` using the new `ResolveStatus` enum
- Add a `isResolved()` helper method
- Add a composable scope for filtering by resolve status (optional, for Filament tabs)

Add to `casts()`:
```php
'resolve_status' => ResolveStatus::class,
```

Add import:
```php
use App\Domain\BankTransactions\ResolveStatus;
```

Add helper methods (optional but recommended):
```php
public function isResolved(): bool
{
    return $this->resolve_status === ResolveStatus::Resolved;
}

public function isUnresolvable(): bool
{
    return $this->resolve_status === ResolveStatus::Unresolvable;
}
```

### 10. Update Service Interface: `app/Domain/BankTransactions/BankTransactionService.php`

Add a new `resolveMatching` method. Domain service orchestrates the full matching flow — repo provides criteria, `TransactionMatchingService` finds matches, repo attaches and marks. Used by both the batch job and Filament (synchronous for single-ID retry).

```php
public function resolveMatching(BankTransactionIdList $ids): void;
```

Add imports:
```php
use App\Domain\BankTransactions\BankTransactionIdList;
```

### 11. Update Service Implementation: `app/Domain/BankTransactions/BankTransactionServiceImpl.php`

Add `TransactionMatchingService` dependency and implement `resolveMatching`. The matching logic lives here — not in the job, not in Filament. Iterates IDs provided by the caller, fetches criteria via repo, delegates match search, attaches and marks.

Changes to the existing constructor and class:

```php
use App\Domain\Invoices\InvoiceId;
use App\Domain\Invoices\InvoiceService;
use App\Domain\PurchaseOrders\PurchaseOrderId;
use App\Domain\PurchaseOrders\PurchaseOrderService;
use Override;

final readonly class BankTransactionServiceImpl implements BankTransactionService
{
    public function __construct(
        private BankTransactionRepository $repository,
        private InvoiceService $invoiceService,
        private PurchaseOrderService $purchaseOrderService,
        private TransactionMatchingService $matchingService,
    ) {}

    // ... existing attachInvoice, attachPurchaseOrder, complete methods unchanged ...

    #[Override]
    public function resolveMatching(BankTransactionIdList $ids): void
    {
        $criteriaList = $this->repository->getMatchCriteriaForIds($ids);

        foreach ($criteriaList as $bankTransactionId => $criteria) {
            $result = $this->matchingService->findMatch($criteria);

            if (! $result->isMatch) {
                $this->repository->markAsUnresolvable($bankTransactionId);
                continue;
            }

            if ($result->invoiceId !== null) {
                $this->repository->attachInvoice($bankTransactionId, $result->invoiceId);
            } elseif ($result->purchaseOrderId !== null) {
                $this->repository->attachPurchaseOrder($bankTransactionId, $result->purchaseOrderId);
            }

            $this->repository->markAsResolved($bankTransactionId);
        }
    }
}
```

### 12. Update Repository Interface: `app/Domain/BankTransactions/BankTransactionRepository.php`

Add `getMatchCriteriaForIds` alongside existing new methods. Returns criteria keyed by `BankTransactionId` so the service can iterate without touching Eloquent.

```php
/**
 * Get MatchCriteria for the given banking transaction IDs.
 * Only returns criteria for IDs that exist (silently skips missing).
 *
 * @return array<BankTransactionId, MatchCriteria>
 */
public function getMatchCriteriaForIds(BankTransactionIdList $ids): array;
```

Add import:
```php
use App\Domain\BankTransactions\MatchCriteria;
```

Full set of new methods on `BankTransactionRepository`:

```php
/** @return BankTransactionIdList */
public function getUnresolvedIds(int $limit): BankTransactionIdList;

/** @return array<BankTransactionId, MatchCriteria> */
public function getMatchCriteriaForIds(BankTransactionIdList $ids): array;

public function markAsResolved(BankTransactionId $bankTransactionId): void;

public function markAsUnresolvable(BankTransactionId $bankTransactionId): void;
```

Add imports:
```php
use App\Domain\BankTransactions\BankTransactionIdList;
use App\Domain\BankTransactions\MatchCriteria;
```

### 13. Update Repository Interface: `app/Domain/Invoices/InvoiceRepository.php`

Add one new method:

```php
/**
 * Find an open or pending Invoice that matches the given credit criteria.
 * Matches on: member's banking_account_number, amount (within tolerance), date (±30 days).
 * Returns the best match (closest amount), or null.
 */
public function findMatchingCredit(string $bankingAccountNumber, float $amount, DateTimeInterface $date): ?InvoiceId;
```

Add import:
```php
use App\Domain\Invoices\InvoiceId;
```

### 14. Update Repository Interface: `app/Domain/PurchaseOrders/PurchaseOrderRepository.php`

Add one new method:

```php
/**
 * Find an open or pending PurchaseOrder that matches the given debit criteria.
 * Matches on: creditor_iban, amount (within tolerance), date (±30 days).
 * Returns the best match (closest amount), or null.
 */
public function findMatchingDebit(string $creditorIban, float $amount, \DateTimeInterface $date): ?PurchaseOrderId;
```

Add imports:
```php
use App\Domain\PurchaseOrders\PurchaseOrderId;
```

### 15. Update Repository Implementation: `app/Infrastructure/BankTransactions/BankTransactionDbRepository.php`

Add five new methods implementing the interface additions. The `getMatchCriteriaForIds` fetches raw columns via Eloquent (only here, in the infrastructure layer) and maps to `MatchCriteria` DTOs.

```php
#[Override]
public function getMatchCriteriaForIds(BankTransactionIdList $ids): array
{
    $rawIds = array_map(static fn (BankTransactionId $id): int => $id->value, $ids->ids);

    if ($rawIds === []) {
        return [];
    }

    return BankingTransaction::query()
        ->whereIn('id', $rawIds)
        ->get()
        ->mapWithKeys(static fn (BankingTransaction $bt): array => [
            BankTransactionId::create($bt->id)->value => new MatchCriteria(
                date: $bt->date,
                amount: (float) $bt->amount,
                bankingAccountNumber: $bt->banking_account_number,
            ),
        ])
        ->all();
}

#[Override]
public function getUnresolvedIds(int $limit): BankTransactionIdList
{
    $ids = BankingTransaction::query()
        ->where('resolve_status', 'unresolved')
        ->orderBy('date', 'desc')
        ->limit($limit)
        ->pluck('id')
        ->map(fn (int $id) => BankTransactionId::create($id))
        ->all();

    return new BankTransactionIdList($ids);
}

#[Override]
public function markAsResolved(BankTransactionId $bankTransactionId): void
{
    BankingTransaction::query()
        ->where('id', $bankTransactionId->value)
        ->update(['resolve_status' => 'resolved']);
}

#[Override]
public function markAsUnresolvable(BankTransactionId $bankTransactionId): void
{
    BankingTransaction::query()
        ->where('id', $bankTransactionId->value)
        ->update(['resolve_status' => 'unresolvable']);
}
```

Add imports:
```php
use App\Domain\BankTransactions\BankTransactionIdList;
use App\Domain\BankTransactions\MatchCriteria;
```

### 16. Update Repository Implementation: `app/Infrastructure/Invoices/InvoiceRepositoryDb.php`

Add `findMatchingCredit` method implementation:

```php
#[Override]
public function findMatchingCredit(string $bankingAccountNumber, float $amount, DateTimeInterface $date): ?InvoiceId
{
    $startDate = CarbonImmutable::instance($date)->subDays(30);
    $endDate = CarbonImmutable::instance($date)->addDays(30);

    $invoice = Invoice::query()
        ->whereIn('status', [InvoiceStatus::Open, InvoiceStatus::Pending])
        ->whereBetween('date', [$startDate, $endDate])
        ->whereHas('member.paymentInformation', function ($query) use ($bankingAccountNumber) {
            $query->where('banking_account_number', $bankingAccountNumber);
        })
        ->orderByAmountProximity($amount)
        ->with('lines')
        ->first();

    if ($invoice === null) {
        return null;
    }

    // Use the model's total accessor (no separate lines query needed)
    if (abs($invoice->total->price - $amount) > self::AMOUNT_TOLERANCE) {
        return null;
    }

    return InvoiceId::create($invoice->id);
}
```

Add constant:
```php
private const float AMOUNT_TOLERANCE = 0.01;
```

Add imports:
```php
use App\Domain\Invoices\InvoiceId;
use App\Domain\Invoices\InvoiceStatus;
use Carbon\CarbonImmutable;
```

### 17. Update Repository Implementation: `app/Infrastructure/PurchaseOrders/PurchaseOrderRepositoryDb.php`

Add `findMatchingDebit` method implementation:

```php
#[Override]
public function findMatchingDebit(string $creditorIban, float $amount, \DateTimeInterface $date): ?PurchaseOrderId
{
    $startDate = CarbonImmutable::instance($date)->subDays(30);
    $endDate = CarbonImmutable::instance($date)->addDays(30);

    $purchaseOrder = PurchaseOrder::query()
        ->whereIn('status', [PurchaseOrderStatus::Open, PurchaseOrderStatus::Pending])
        ->whereBetween('date', [$startDate, $endDate])
        ->where('creditor_iban', $creditorIban)
        ->orderByRelevancy($amount, $creditorIban)
        ->with('lines')
        ->first();

    if ($purchaseOrder === null) {
        return null;
    }

    // Use the model's total accessor (no separate lines query needed)
    if (abs($purchaseOrder->total->price - $amount) > self::AMOUNT_TOLERANCE) {
        return null;
    }

    return PurchaseOrderId::create($purchaseOrder->id);
}
```

Add constant:
```php
private const float AMOUNT_TOLERANCE = 0.01;
```

Add imports:
```php
use App\Domain\PurchaseOrders\PurchaseOrderId;
use App\Domain\PurchaseOrders\PurchaseOrderStatus;
use App\Models\PurchaseOrderLine;
use Carbon\CarbonImmutable;
```

### 18. Update Filament Table: `app/Filament/Admin/Resources/BankingTransactions/Tables/BankingTransactionsTable.php`

Add a `resolve_status` column between the existing `status` and `banking_account_number` columns:

```php
TextColumn::make('resolve_status')
    ->label(__('labels.resolve_status'))
    ->badge()
    ->formatStateUsing(static fn ($state): string => match ($state?->value ?? $state) {
        'unresolved' => __('labels.resolve_status_unresolved'),
        'resolved' => __('labels.resolve_status_resolved'),
        'unresolvable' => __('labels.resolve_status_unresolvable'),
        default => (string) ($state?->value ?? $state),
    })
    ->color(static fn ($state): string => match ($state?->value ?? $state) {
        'resolved' => 'success',
        'unresolvable' => 'danger',
        default => 'warning',
    })
    ->sortable(),
```

### 19. Update Filament View Page: `app/Filament/Admin/Resources/BankingTransactions/Pages/ViewBankingTransaction.php`

Add a "Retry Matching" action to the header actions, visible only when `resolve_status === 'unresolvable'`:

```php
use App\Filament\Admin\Resources\BankingTransactions\Actions\RetryMatchingAction;
```

In `getHeaderActions()`:
```php
protected function getHeaderActions(): array
{
    return [
        RetryMatchingAction::make(),
        CompleteBankingTransactionAction::make(),
        EditAction::make(),
        DeleteAction::make(),
    ];
}
```

### 20. New Filament Action: `app/Filament/Admin/Resources/BankingTransactions/Actions/RetryMatchingAction.php`

Calls `BankTransactionService::resolveMatching` directly — synchronous, instant feedback. No job dispatch for single-ID retry.

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankingTransactions\Actions;

use App\Domain\BankTransactions\BankTransactionId;
use App\Domain\BankTransactions\BankTransactionIdList;
use App\Domain\BankTransactions\BankTransactionRepository;
use App\Domain\BankTransactions\BankTransactionService;
use App\Domain\BankTransactions\BankTransactionStatus;
use App\Domain\BankTransactions\ResolveStatus;
use App\Models\BankingTransaction;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

final class RetryMatchingAction
{
    public static function make(): Action
    {
        return Action::make('retryMatching')
            ->label(__('labels.retry_matching'))
            ->color('warning')
            ->icon('heroicon-o-arrow-path')
            ->visible(static fn (BankingTransaction $record): bool =>
                $record->resolve_status === ResolveStatus::Unresolvable && $record->status === BankTransactionStatus::Open
            )
            ->requiresConfirmation()
            ->action(static function (
                BankingTransaction $record,
                BankTransactionRepository $repository,
                BankTransactionService $service,
            ): void {
                $bankTransactionId = BankTransactionId::create($record->id);

                $service->resolveMatching(new BankTransactionIdList([$bankTransactionId]));
            })
            ->successNotificationTitle(__('labels.retry_matching_completed'))
            ->after(static fn (ViewRecord $livewire) => $livewire->dispatch('refresh'));
    }
}
```

### 21. Update Filament Create Page: `app/Filament/Admin/Resources/BankingTransactions/Pages/CreateBankingTransaction.php`

Override `afterCreate()` to call `BankTransactionService::resolveMatching` directly (synchronous, single transaction — no job needed):

```php
use App\Domain\BankTransactions\BankTransactionId;
use App\Domain\BankTransactions\BankTransactionIdList;
use App\Domain\BankTransactions\BankTransactionService;
```

Add method:
```php
#[Override]
protected function afterCreate(): void
{
    $bankTransactionId = BankTransactionId::create($this->record->id);
    $service = app(BankTransactionService::class);
    $service->resolveMatching(new BankTransactionIdList([$bankTransactionId]));
}
```

### 22. Update Filament List Page: `app/Filament/Admin/Resources/BankingTransactions/Pages/ListBankingTransactions.php`

After import, dispatch a batch matching job. Import creates many transactions — async job appropriate here.

```php
use App\Domain\Jobs\MatchBankingTransactionsJob;
```

After the notification success, add:
```php
// Dispatch background matching job for newly imported transactions
MatchBankingTransactionsJob::dispatch();
```

The full modified action callback:
```php
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

    // Dispatch background matching for newly imported transactions
    if ($result['imported'] > 0) {
        MatchBankingTransactionsJob::dispatch();
    }

    $livewire->dispatch('refreshTable');
}),
```

### 23. Update Scheduler: `routes/console.php`

Add the new command to the scheduler:

```php
use App\Console\Commands\MatchBankingTransactionsCommand;

Schedule::command(MatchBankingTransactionsCommand::class)->hourly();
```

### 24. Add Lang Labels: `lang/nl/labels.php`

Add the following new labels after the existing `'completed' => 'Afgerond'` entry (around line 250):

```php
'resolve_status' => 'Koppeling status',
'resolve_status_unresolved' => 'Niet gekoppeld',
'resolve_status_resolved' => 'Gekoppeld',
'resolve_status_unresolvable' => 'Niet koppelbaar',
'retry_matching' => 'Opnieuw koppelen',
'retry_matching_completed' => 'Koppeling opnieuw uitgevoerd',
```

### 25. Update Filament List Page Tabs: `app/Filament/Admin/Resources/BankingTransactions/Pages/ListBankingTransactions.php`

Optionally add tabs for filtering by resolve status. Add to `getTabs()`:

```php
'unresolvable' => Tabs\Tab::make(__('labels.resolve_status_unresolvable'))->modifyQueryUsing(static function ($query) {
    return $query->where('resolve_status', 'unresolvable');
}),
```

---

## Matching Algorithm Details

### Credit (positive amount) → Invoice matching

Execute query:
```sql
SELECT invoices.*
FROM invoices
JOIN members ON members.id = invoices.member_id
JOIN payment_information ON payment_information.member_id = members.id
WHERE invoices.status IN ('open', 'pending')
  AND invoices.date BETWEEN :startDate AND :endDate  -- ±30 days
  AND payment_information.banking_account_number = :iban
ORDER BY ABS(COALESCE(
  (SELECT SUM(price * quantity) FROM invoice_lines WHERE invoice_lines.invoice_id = invoices.id), 0
) - :amount) ASC
LIMIT 1
```

Then verify: `ABS(total - :amount) <= 0.01` (tolerance check). If not within tolerance, return null.

### Debit (negative amount) → PurchaseOrder matching

Execute query:
```sql
SELECT purchase_orders.*
FROM purchase_orders
WHERE purchase_orders.status IN ('open', 'pending')
  AND purchase_orders.date BETWEEN :startDate AND :endDate  -- ±30 days
  AND purchase_orders.creditor_iban = :iban
ORDER BY ABS(COALESCE(
  (SELECT SUM(price) FROM purchase_order_lines WHERE purchase_order_lines.purchase_order_id = purchase_orders.id), 0
) - :amount) ASC
LIMIT 1
```

Then verify: `ABS(total - :amount) <= 0.01` (tolerance check). If not within tolerance, return null.

### Key design decisions

1. **Best match strategy**: When multiple candidates exist (same IBAN, similar date), pick the one with the **closest amount**. This uses the existing `orderByAmountProximity` / `orderByRelevancy` scopes.

2. **Tolerance**: `0.01` EUR tolerance for amount comparison. This handles floating-point imprecision and minor rounding differences.

3. **Date range**: ±30 days from the banking transaction date. This is "roughly in the same period" as specified.

4. **Single match per transaction**: The matching service returns at most one match. This simplifies the linking logic. The match is the "best" one (closest amount).

5. **Resolve status is independent of `status`**: The existing `status` field (`open`/`completed`) tracks financial balancing. The new `resolve_status` field (`unresolved`/`resolved`/`unresolvable`) tracks whether matching was attempted and succeeded. A transaction can be `resolved` (linked to an invoice) but still `open` (not yet balanced/completed).

---

## Test Plan

### 26. Unit Test: `tests/Unit/Domain/BankTransactions/TransactionMatchingServiceImplTest.php`

Test cases:
- `test_find_match_positive_amount_returns_match_result_with_invoice`: Mock `InvoiceRepository::findMatchingCredit` returns InvoiceId → service returns `MatchResult::foundInvoice(...)`, `$result->isMatch === true`, `$result->invoiceId !== null`
- `test_find_match_positive_amount_no_match_returns_none`: Mock returns null → service returns `MatchResult::none()`, `$result->isMatch === false`
- `test_find_match_negative_amount_returns_match_result_with_purchase_order`: Mock `PurchaseOrderRepository::findMatchingDebit` returns PurchaseOrderId → service returns `MatchResult::foundPurchaseOrder(...)`, `$result->isMatch === true`, `$result->purchaseOrderId !== null`
- `test_find_match_negative_amount_no_match_returns_none`: Mock returns null → service returns `MatchResult::none()`, `$result->isMatch === false`
- `test_find_match_zero_amount_tries_purchase_order`: Amount 0.0 → delegates to `findMatchingDebit`, returns whatever repo gives

### 27. Unit Test: `tests/Unit/Domain/Jobs/MatchBankingTransactionsJobTest.php`

Job is now batch-only. Tests verify thin orchestration — fetch IDs from repo, delegate to service:

- `test_job_fetches_unresolved_and_calls_service`: Mock `getUnresolvedIds` returns IDs → verify `resolveMatching` called with correct `BankTransactionIdList`
- `test_job_skips_when_no_unresolved_ids`: Mock returns empty list → `resolveMatching` never called
- `test_job_respects_batch_size`: Default `$batchSize = 50`, verify `getUnresolvedIds(50)` called

Create/update expectation classes:
- `tests/Unit/Domain/BankTransactions/BankTransactionRepositoryExpectation.php` — UPDATE existing file to add:
  - `expectsGetUnresolvedIds(int $limit, BankTransactionIdList $return)`
  - `expectsGetMatchCriteriaForIds(BankTransactionIdList $ids, array $return)`
  - `expectsMarkAsResolved(BankTransactionId $id)`
  - `expectsMarkAsUnresolvable(BankTransactionId $id)`
- `tests/Unit/Domain/BankTransactions/BankTransactionServiceExpectation.php` — NEW, with:
  - `expectsResolveMatching(BankTransactionIdList $ids)`

### 28. Unit Test: `tests/Unit/Domain/BankTransactions/BankTransactionServiceImplTest.php`

Add new test for `resolveMatching`:

- `test_resolve_matching_with_match_calls_attach_and_mark_resolved`: Mock `getMatchCriteriaForIds` returns criteria, mock `findMatch` returns match → verify `attachInvoice`/`attachPurchaseOrder` and `markAsResolved` called
- `test_resolve_matching_without_match_marks_unresolvable`: Mock `findMatch` returns `MatchResult::none()` → verify `markAsUnresolvable` called, no attach
- `test_resolve_matching_with_multiple_ids`: Mixed results → each ID handled independently

### 29. Feature Test: `tests/Feature/Infrastructure/BankTransactions/BankTransactionDbRepositoryTest.php`

Test cases for the new repository methods:
- `test_get_unresolved_ids_returns_only_unresolved`: Create transactions with different resolve_statuses, verify only unresolved returned
- `test_get_unresolved_ids_respects_limit`: Create 10 unresolved, limit 5 → returns 5
- `test_mark_as_resolved_updates_status`: Create unresolved transaction, mark → verify
- `test_mark_as_unresolvable_updates_status`: Same pattern
- `test_reset_resolve_status_resets_to_unresolved`: Mark as unresolvable, reset → verify unresolved

### 30. Feature Test: `tests/Feature/Infrastructure/Invoices/InvoiceRepositoryDbMatchingTest.php`

Test cases:
- `test_find_matching_credit_finds_exact_match`: Create Invoice with matching IBAN, amount, date → verify found
- `test_find_matching_credit_returns_null_for_wrong_iban`: Different IBAN → null
- `test_find_matching_credit_returns_null_for_wrong_amount`: Outside tolerance → null
- `test_find_matching_credit_returns_null_for_wrong_date`: Outside 30-day window → null
- `test_find_matching_credit_ignores_paid_invoices`: Invoice is paid → null
- `test_find_matching_credit_ignores_declined_invoices`: Invoice is declined → null
- `test_find_matching_credit_picks_closest_amount`: Two matching candidates → returns closest amount one

### 31. Feature Test: `tests/Feature/Infrastructure/PurchaseOrders/PurchaseOrderRepositoryDbMatchingTest.php`

Same pattern as Invoice matching tests, but for PurchaseOrder:
- Exact match, wrong IBAN, wrong amount, wrong date, only open/pending, closest amount wins.

### 32. Test for Filament Retry Action (Feature Test)

Test the `RetryMatchingAction`:
- Button visible when `resolve_status === 'unresolvable'`
- Button hidden when `resolve_status !== 'unresolvable'`
- Clicking dispatches `MatchBankingTransactionsJob` with correct transaction ID

---

## Implementation Order (Recommended)

1. **Migration** (#8) — Add `resolve_status` column
2. **Enum + DTOs** (#1, #2, #3, #4) — `ResolveStatus`, `MatchCriteria`, `BankTransactionIdList`, `MatchResult`
3. **Model update** (#9) — Cast resolve_status on BankingTransaction
4. **Repository interfaces** (#12, #13, #14) — Add new methods to all three repos
5. **Repository implementations** (#15, #16, #17) — DB implementations including `getMatchCriteriaForIds`
6. **Domain services** (#5, #6, #10, #11) — `TransactionMatchingService` interface + impl, `BankTransactionService::resolveMatching`
7. **Job** (#7) — Thin batch-only `MatchBankingTransactionsJob`
8. **Artisan command** (#8) — Console command for scheduler
9. **Lang labels** (#24) — Dutch translations
10. **Filament table** (#18) — Add resolve_status column
11. **Filament actions** (#19, #20) — View page + RetryMatchingAction (calls service directly)
12. **Filament create page** (#21) — Call service directly after create
13. **Filament list page** (#22) — Dispatch batch job after import + optional tabs (#25)
14. **Scheduler** (#23) — Add to routes/console.php
15. **Tests** (#26–#32) — Write all tests

---

## Potential Risks & Mitigations

| Risk | Mitigation |
|------|-----------|
| Multiple transactions matching the same Invoice/PO | The `banking_transaction_references` table has a unique constraint on `(banking_transaction_id, reference_type, reference_id)`. Multiple transactions can reference the same Invoice/PO (this is intentional — e.g., partial payments). |
| Amount mismatch between transaction and Invoice/PO total | The 0.01 tolerance handles floating-point. If mismatches still occur, the transaction will be marked `unresolvable` and can be manually matched via the existing attach actions. |
| Job timeout on large batches | `$batchSize = 50` keeps per-job processing small. The hourly scheduler ensures remaining items get picked up. |
| Transactions created during job execution are missed | The hourly scheduler catches them. Individual create/import also dispatches the job immediately. |
| `banking_account_number` vs `creditor_iban` naming inconsistency | The banking transaction uses `banking_account_number` (own account), PurchaseOrder uses `creditor_iban` (creditor's account). These match because the transaction's `banking_account_number` records the **counterparty**'s account number in the MT940 format. |

---

## Workflow Summary

```
┌──────────────────┐     ┌──────────────────────┐
│ Create (Filament)│────▶│ BankTransactionService │──── Synchronous, single ID
│ Retry (Filament) │     │ ::resolveMatching()   │
└──────────────────┘     └──────────┬───────────┘
                                    │
┌──────────────────┐                │
│ Import MT940     │──┐             │
│ (Filament)       │  │             │
└──────────────────┘  │             │
                      ▼             ▼
┌──────────────────┐     ┌──────────────────────────┐
│ Scheduler        │────▶│ MatchBankingTransactions  │──── Async, batch (≤50)
│ (hourly)         │     │ Job                       │
└──────────────────┘     └──────────┬───────────────┘
                                    │
                        repo->getUnresolvedIds($batchSize)
                        service->resolveMatching($ids)
                                    │
                        ┌───────────▼──────────────┐
                        │ BankTransactionRepository │
                        │ ::getMatchCriteriaForIds  │  ← Eloquent query only here
                        └───────────┬──────────────┘
                                    │
                        ┌───────────▼──────────────┐
                        │ TransactionMatchingService│
                        │ ::findMatch(criteria)     │
                        └───────────┬──────────────┘
                                    │
                    ┌───────────────┼───────────────┐
                    │ amount > 0    │               │ amount < 0
                    ▼               │               ▼
          ┌─────────────────┐      │     ┌─────────────────────┐
          │ InvoiceRepository│      │     │ PurchaseOrderRepo   │
          │ findMatchingCredit      │     │ findMatchingDebit   │
          └───────┬─────────┘      │     └───────┬─────────────┘
                  │                │             │
          ┌───────▼─────────┐      │     ┌───────▼─────────────┐
          │ MatchResult     │      │     │ MatchResult          │
          └───┬─────────┬───┘      │     └───┬─────────┬───────┘
         found │         │ none    │    found │         │ none
               ▼         ▼         │          ▼         ▼
        attachInvoice  markAs     │  attachPO  markAs
        markAsResolved Unresolvable│  markAsResolved Unresolvable
                                  │
        ┌─────────────────────────┘
        │
        ▼
  ┌────────────────────┐      ┌────────────────────┐
  │ resolve_status =    │      │ resolve_status =    │
  │ 'resolved'          │      │ 'unresolvable'      │
  │ (linked to Invoice  │      │ (retry via Filament │
  │  or PurchaseOrder)  │      │  button → calls     │
  └────────────────────┘      │  service directly)  │
                              └────────────────────┘
```

**Scheduler**: Runs `app:match-banking-transactions` hourly as a safety net. **Create page** and **Retry button** call `BankTransactionService::resolveMatching()` directly (synchronous, instant feedback). **Import** dispatches the async batch job.

---

## Appendix: SQLite Compatibility Notes

This project uses SQLite as the default database. The queries use standard SQL features available in SQLite:
- `SUM(price * quantity)` — compatible
- `ABS(...)` — compatible
- `CASE WHEN ... THEN ... END` — compatible
- `COALESCE(...)` — compatible
- `STRFTIME('%Y', date)` — already used in existing code (see `BankingTransactionsTable`)
- `date BETWEEN :start AND :end` — compatible
