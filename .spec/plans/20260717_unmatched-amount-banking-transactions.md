# Implementation Plan: Unmatched Amount on Banking Transactions

**Date**: 2026-07-17  
**TODO Item**: #3 — Calculated "unmatched amount" field on BankingTransaction  
**Status**: Ready for implementation

---

## Overview

Add a calculated `unmatchedAmount` to `BankingTransaction` that shows:
```
transaction.amount - SUM(attached bookkeeping records' total)
```
Where each bookkeeping record's total = `amount_price + amount_vat`.

This field must appear on:
1. **View page** — in the content tab (form schema, read-only)
2. **List/table page** — as a sortable column with monetary formatting

The table must **NOT** show the "amount of attached records" — just the unmatched amount.

---

## Architecture Decision: Why Form Schema (not Infolist) for the View Page

The codebase uses **zero infolist components**. All ViewRecord pages render the content tab using the form schema in read-only mode (via `ViewRecord::fillForm()` → `disabled()` with `operation('view')`).

Attempting to introduce an infolist would require either:
- Replacing the entire content tab (losing existing form fields), or
- Complex overrides to show both form and infolist simultaneously

The pragmatic path: add `unmatched_amount` as a computed, read-only field in the existing `BankingTransactionForm` schema. It is hidden on the create page and displayed as a disabled TextInput on the view page — conforming to every other read-only field in the application.

---

## Files to Modify

| # | File | Change |
|---|------|--------|
| 1 | `app/Models/BankingTransaction.php` | Add `unmatchedAmount()` accessor |
| 2 | `app/Filament/Admin/Resources/BankingTransactions/Tables/BankingTransactionsTable.php` | Add `unmatched_amount` column + subquery in `modifyQueryUsing` |
| 3 | `app/Filament/Admin/Resources/BankingTransactions/Schemas/BankingTransactionForm.php` | Add read-only `unmatched_amount` form field (hidden on create) |
| 4 | `app/Filament/Admin/Resources/BankingTransactions/Pages/ViewBankingTransaction.php` | Eager load `bookkeepingRecords` for N+1 prevention |
| 5 | `lang/nl/labels.php` | Add `unmatched_amount` label (Dutch: "Openstaand bedrag") |

## Files to Create

| # | File | Purpose |
|---|------|---------|
| 6 | `tests/Unit/Domain/BankTransactions/BankingTransactionTest.php` | Unit test for `unmatchedAmount()` accessor |
| 7 | Update `tests/Feature/Filament/BankingTransaction/BankingTransactionResourceTest.php` | Feature tests for table column & view page display |

---

## Step-by-Step Implementation

### Step 1: Add Label Translation

**File**: `lang/nl/labels.php`

Add a new entry in the flat array (alphabetically near related banking labels around line 245):

```php
'unmatched_amount' => 'Openstaand bedrag',
```

Also add (for the column header context):
```php
'unmatched_amount' => 'Openstaand bedrag',
```

Place it after line 245 (`'unmatched' => 'Niet gekoppeld'`).

---

### Step 2: Add `unmatchedAmount()` Accessor to the Model

**File**: `app/Models/BankingTransaction.php`

Add the accessor following the existing pattern used by `Invoice::total()` (attribute accessor with `Attribute::get()` — get-only, no setter):

```php
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * @return Attribute<float, never>
 */
protected function unmatchedAmount(): Attribute
{
    return Attribute::get(function (): float {
        $recordsSum = $this->bookkeepingRecords->sum(
            fn (BookkeepingRecord $record): float =>
                (float) $record->amount_price + (float) $record->amount_vat,
        );
        return (float) $this->amount - $recordsSum;
    });
}
```

**Design notes**:
- Uses raw column access (`$record->amount_price`, `$record->amount_vat`) rather than the `CompoundPrice` accessor to avoid unnecessary object instantiation during summation.
- Assumes `bookkeepingRecords` is eager-loaded. The view page will ensure this (Step 5). The accessor itself does NOT call `loadMissing()` — callers are responsible for eager loading, following the principle that lazy-load guards catch missing eager loads in development.
- Return type `float` matches the `amount` column type (`decimal:3` cast).
- Placed after `casts()` method, before the closing `}` of the class.
- Add the appropriate `use` import for `Attribute`: `use Illuminate\Database\Eloquent\Casts\Attribute;`

**PHPDoc addition**: Add `@property-read float $unmatched_amount` to the class-level docblock so static analysis (Larastan) recognizes the virtual attribute.

---

### Step 3: Add Sortable Column to the Table

**File**: `app/Filament/Admin/Resources/BankingTransactions/Tables/BankingTransactionsTable.php`

#### 3a. Add `modifyQueryUsing` with correlated subquery

Add before the `->columns([...])` chain:

```php
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

// Inside configure():
->modifyQueryUsing(static function (Builder $query): Builder {
    return $query->addSelect([
        DB::raw('COALESCE(banking_transactions.amount, 0) - COALESCE(
            (SELECT SUM(bookkeeping_records.amount_price + bookkeeping_records.amount_vat)
             FROM bookkeeping_records
             WHERE bookkeeping_records.banking_transaction_id = banking_transactions.id),
            0
        ) as unmatched_amount'),
    ]);
})
```

**Why `addSelect` with `DB::raw` instead of a subquery builder**:
- The expression involves both a column from the parent table (`banking_transactions.amount`) and an aggregate from the child table. A single `DB::raw()` correlated subquery is the most efficient approach — one SQL query, no extra round trips.
- `COALESCE(amount, 0)` guards against null amounts.
- The inner `COALESCE(SUM(...), 0)` handles transactions with zero attached bookkeeping records.
- Follows the **Laravel Best Practices §2 (Advanced Query Patterns)**: correlated subquery via `addSelect()`.

**Note on `modifyQueryUsing`**:
- Filament rebuilds the table query fresh on each interaction (sort, paginate, filter), so `addSelect` in `modifyQueryUsing` will not create duplicate columns.

#### 3b. Add the `unmatched_amount` column

Add to the `->columns([...])` array, **after** the `amount` column and **before** the `banking_account_number` column:

```php
TextColumn::make('unmatched_amount')
    ->label(__('labels.unmatched_amount'))
    ->money('EUR')
    ->sortable()
    ->alignEnd()
    ->color(static fn (BankingTransaction $record): string =>
        $record->unmatched_amount != 0 ? 'warning' : 'success'
    ),
```

**Column placement rationale**:
- Placed near the `amount` column since they are semantically related (original amount → remaining unmatched amount).
- `->alignEnd()` matches the existing `amount` column style.
- `->money('EUR')` follows **Pattern A** (the standard for raw float columns in this codebase, used by the `amount` column and the `BookkeepingRecordsRelationManager::amount` column).
- `->sortable()` works because `unmatched_amount` is a real column alias in the SQL query (MySQL supports `ORDER BY` on SELECT aliases).
- `->color()` provides a visual indicator: **warning** (amber) when unmatched ≠ 0, **success** (green) when fully matched (0). This makes outstanding amounts immediately visible in the table.

#### 3c. Remove the "amount of attached records" concern

The requirement states: "The table should not show the amount of attached records." The existing `matched_count` column shows a count (number of attached references), not an amount. This is **not** the same thing. The requirement likely refers to NOT adding a separate column that sums the bookkeeping record amounts — we should only add the unmatched amount. The existing `matched_count` column can remain as-is (it shows reference count, not monetary amount).

**No changes needed** to the `matched_count` column. Do NOT add a column that sums bookkeeping record amounts.

---

### Step 4: Add Read-Only Form Field for View Page

**File**: `app/Filament/Admin/Resources/BankingTransactions/Schemas/BankingTransactionForm.php`

Add a new `TextInput` for `unmatched_amount` inside the existing `Section`'s schema array, after the `amount` field:

```php
TextInput::make('unmatched_amount')
    ->label(__('labels.unmatched_amount'))
    ->prefix('€')
    ->numeric()
    ->disabled()
    ->dehydrated(false)
    ->hiddenOn('create')
    ->afterStateHydrated(function (TextInput $component, ?BankingTransaction $record): void {
        if ($record !== null) {
            $component->state($record->unmatchedAmount());
        }
    }),
```

**Field design rationale**:
- `->disabled()` — The field is always read-only (even on edit, which doesn't exist yet but is future-proof).
- `->dehydrated(false)` — Prevents the field from being included in form submission. There is no `unmatched_amount` database column, so attempting to save it would error.
- `->hiddenOn('create')` — Hides the field entirely on the create form where `$record` is null and the concept of "unmatched" is meaningless.
- `->afterStateHydrated()` — Manually sets the state after the form is hydrated (since the model doesn't have an `unmatched_amount` attribute, standard hydration won't fill it).
- `->prefix('€')` + `->numeric()` — Matches the visual style of the existing `amount` field.
- `->numeric()` — Ensures proper number input display (though disabled, it still affects formatting).

**Import additions needed**:
- `use App\Models\BankingTransaction;` — for the `$record` type hint
- `use Filament\Forms\Components\TextInput;` — already imported

**Placement**: Add the field after the `amount` TextInput (line 33) and before `banking_account_number` (line 35). This groups monetary fields together.

---

### Step 5: Eager Load on View Page

**File**: `app/Filament/Admin/Resources/BankingTransactions/Pages/ViewBankingTransaction.php`

Add a `getEloquentQuery()` override to eager load `bookkeepingRecords`:

```php
use Illuminate\Database\Eloquent\Builder;
use Override;

#[Override]
protected function getEloquentQuery(): Builder
{
    return parent::getEloquentQuery()->with('bookkeepingRecords');
}
```

**Why this is needed**:
- The `unmatchedAmount()` accessor accesses `$this->bookkeepingRecords`. Without eager loading, this triggers an N+1 query.
- Although the view page loads only one record (so N+1 = 2 queries, not severe), following **Laravel Best Practices §1 (Database Performance)** — always eager load.
- If `Model::preventLazyLoading()` is enabled in development, without this the page would throw a `LazyLoadingViolationException`.

**Placement**: Add the method before `getHeaderActions()` (around line 18).

**Import additions needed**:
- `use Illuminate\Database\Eloquent\Builder;`

---

### Step 6: Unit Test for the Accessor

**File (CREATE)**: `tests/Unit/Domain/BankTransactions/BankingTransactionTest.php`

**Test class structure**:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\BankTransactions;

use App\Models\BankingTransaction;
use App\Models\BookkeepingRecord;
use PHPUnit\Framework\Attributes\Test;
use Tests\UnitTestCase;

final class BankingTransactionTest extends UnitTestCase
{
    // Tests here
}
```

**Test methods**:

#### 6a. `test_unmatched_amount_returns_full_amount_when_no_bookkeeping_records`

```php
#[Test]
public function test_unmatched_amount_returns_full_amount_when_no_bookkeeping_records(): void
{
    $transaction = new BankingTransaction(['amount' => '150.500']);
    $transaction->setRelation('bookkeepingRecords', collect([]));

    $result = $transaction->unmatchedAmount();

    $this->assertSame(150.5, $result);
}
```

**Verification**: When there are no bookkeeping records, unmatched = full transaction amount.

#### 6b. `test_unmatched_amount_subtracts_sum_of_bookkeeping_record_totals`

```php
#[Test]
public function test_unmatched_amount_subtracts_sum_of_bookkeeping_record_totals(): void
{
    $transaction = new BankingTransaction(['amount' => '200.000']);
    $transaction->setRelation('bookkeepingRecords', collect([
        new BookkeepingRecord(['amount_price' => '50.000', 'amount_vat' => '10.500']),
        new BookkeepingRecord(['amount_price' => '25.000', 'amount_vat' => '5.250']),
    ]));

    $result = $transaction->unmatchedAmount();

    // 200.0 - (50.0 + 10.5 + 25.0 + 5.25) = 200.0 - 90.75 = 109.25
    $this->assertSame(109.25, $result);
}
```

**Verification**: Correctly computes: `transaction.amount - SUM(amount_price + amount_vat)`.

#### 6c. `test_unmatched_amount_returns_zero_when_fully_matched`

```php
#[Test]
public function test_unmatched_amount_returns_zero_when_fully_matched(): void
{
    $transaction = new BankingTransaction(['amount' => '100.000']);
    $transaction->setRelation('bookkeepingRecords', collect([
        new BookkeepingRecord(['amount_price' => '60.000', 'amount_vat' => '0.000']),
        new BookkeepingRecord(['amount_price' => '40.000', 'amount_vat' => '0.000']),
    ]));

    $result = $transaction->unmatchedAmount();

    $this->assertSame(0.0, $result);
}
```

**Verification**: When bookkeeping records exactly match the transaction amount, unmatched = 0.

#### 6d. `test_unmatched_amount_handles_negative_transaction_amount`

```php
#[Test]
public function test_unmatched_amount_handles_negative_transaction_amount(): void
{
    $transaction = new BankingTransaction(['amount' => '-50.000']);
    $transaction->setRelation('bookkeepingRecords', collect([
        new BookkeepingRecord(['amount_price' => '20.000', 'amount_vat' => '4.200']),
    ]));

    $result = $transaction->unmatchedAmount();

    // -50.0 - (20.0 + 4.2) = -50.0 - 24.2 = -74.2
    $this->assertSame(-74.2, $result);
}
```

**Verification**: Negative transaction amounts (e.g., debits/expenses) are handled correctly.

---

### Step 7: Feature Tests

**File (UPDATE)**: `tests/Feature/Filament/BankingTransaction/BankingTransactionResourceTest.php`

#### 7a. Test that the list page shows the unmatched amount column

```php
#[Test]
public function test_list_page_shows_unmatched_amount_column(): void
{
    $this->withAuthorizedUser();

    $transaction = BankingTransaction::factory()->create(['amount' => 200.000]);
    BookkeepingRecord::factory()->create([
        'banking_transaction_id' => $transaction->id,
        'amount_price' => 80.000,
        'amount_vat' => 16.800,
    ]);

    Livewire::test(ListBankingTransactions::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords(BankingTransaction::all());
}
```

**Verification**: The page renders without errors, the table displays the record (the `unmatched_amount` column is part of the table; Filament's table assertions implicitly validate that all defined columns render). For explicit assertion, check for the label:

```php
->assertSee(__('labels.unmatched_amount'))
```

#### 7b. Test that the view page renders with unmatched amount in form

```php
#[Test]
public function test_view_page_shows_unmatched_amount(): void
{
    $this->withAuthorizedUser();

    $transaction = BankingTransaction::factory()->create(['amount' => 300.000]);
    BookkeepingRecord::factory()->create([
        'banking_transaction_id' => $transaction->id,
        'amount_price' => 100.000,
        'amount_vat' => 21.000,
    ]);
    BookkeepingRecord::factory()->create([
        'banking_transaction_id' => $transaction->id,
        'amount_price' => 50.000,
        'amount_vat' => 10.500,
    ]);

    // 300.0 - (100+21+50+10.5) = 300.0 - 181.5 = 118.5

    Livewire::test(ViewBankingTransaction::class, ['record' => $transaction->id])
        ->assertSuccessful()
        ->assertSee(__('labels.unmatched_amount'));
}
```

**Note on Filament view page testing**: `ViewBankingTransaction` extends `ViewRecord`. To test it via Livewire, pass `['record' => $transaction->id]` as the parameter. The content tab renders the form schema with the computed field.

#### 7c. Add import for ViewBankingTransaction

Add at the top of the file:
```php
use App\Filament\Admin\Resources\BankingTransactions\Pages\ViewBankingTransaction;
```

#### 7d. Add import for BookkeepingRecord

```php
use App\Models\BookkeepingRecord;
```

---

### Step 8: Run Tests

After implementation, run:

```bash
# Unit test
./Taskfile artisan test --compact --filter="test_unmatched_amount" tests/Unit/Domain/BankTransactions/BankingTransactionTest.php

# Feature tests
./Taskfile artisan test --compact --filter="test_list_page_shows_unmatched_amount_column|test_view_page_shows_unmatched_amount" tests/Feature/Filament/BankingTransaction/BankingTransactionResourceTest.php

# Full BankingTransaction test suite
./Taskfile artisan test --compact tests/Feature/Filament/BankingTransaction/BankingTransactionResourceTest.php
./Taskfile artisan test --compact tests/Unit/Domain/BankTransactions/BankingTransactionTest.php
```

---

## Performance Edge Cases

### 1. Large number of bookkeeping records per transaction

The correlated subquery `SUM(amount_price + amount_vat)` runs once per banking transaction row. With thousands of transactions and hundreds of bookkeeping records each, this is an indexed lookup on `bookkeeping_records.banking_transaction_id`. The existing migration already has an index on `banking_transaction_id`:

```sql
-- From 2026_07_05_075109_add_banking_transaction_id_to_bookkeeping_records_table.php
$table->index('banking_transaction_id');
```

**No additional migration needed** — the index already exists.

### 2. Sorting performance on the list page

When sorting by `unmatched_amount`, MySQL performs the correlated subquery for every row and then sorts. For very large datasets (100K+ transactions), this can be slow because each row requires a subquery execution.

**Mitigation**: If performance becomes an issue, consider denormalizing with a stored `unmatched_amount` column updated via an observer on `BookkeepingRecord` create/update/delete and on `BankingTransaction` amount changes. This is **out of scope** for this plan — only implement if profiling shows a real bottleneck.

### 3. View page: single record, no N+1 risk

The view page loads only one `BankingTransaction` with its `bookkeepingRecords` eager-loaded. This is always 2 queries regardless of record count.

### 4. Table: the subquery approach vs. eager loading

| Approach | Queries | Memory |
|----------|---------|--------|
| Eager-load all bookkeepingRecords | 2 queries (1 for transactions + 1 for ALL bookkeeping records) | O(total records) |
| Correlated subquery | 1 query (subquery inline) | O(0) extra |

The subquery approach is superior: no PHP memory overhead, single query, and leveraged by the existing `banking_transaction_id` index.

---

## Review Checklist (for Implementer)

- [ ] `unmatchedAmount()` accessor uses raw column access (`amount_price`, `amount_vat`) not `CompoundPrice` for performance
- [ ] Accessor does NOT call `loadMissing()` — callers are responsible
- [ ] Form field has `->dehydrated(false)` to prevent save errors
- [ ] Form field has `->hiddenOn('create')` to avoid showing on create page
- [ ] Table column uses `->money('EUR')` consistent with the `amount` column
- [ ] Table column uses `->sortable()` — works because of the SQL subquery alias
- [ ] `modifyQueryUsing` uses `addSelect(DB::raw(...))` for the correlated subquery
- [ ] `COALESCE` wraps both the transaction amount and the SUM to handle nulls
- [ ] View page eager loads `bookkeepingRecords` via `getEloquentQuery()`
- [ ] Labels added to `lang/nl/labels.php` in alphabetical position
- [ ] PHPDoc `@property-read float $unmatched_amount` added to model class docblock
- [ ] All imports added (`Attribute`, `Builder`, `DB`, `BookkeepingRecord`, `ViewBankingTransaction`)
- [ ] Unit test covers: no records, partial match, full match, negative amount
- [ ] Feature tests cover: list page renders with column, view page renders with field
- [ ] All tests pass

---

## Implementation Order

1. **Labels** (`lang/nl/labels.php`) — foundation for all display text
2. **Model accessor** (`app/Models/BankingTransaction.php`) — core business logic
3. **Table column** (`BankingTransactionsTable.php`) — list page display
4. **Form field** (`BankingTransactionForm.php`) — view page display
5. **View page eager loading** (`ViewBankingTransaction.php`) — N+1 guard
6. **Unit tests** (`BankingTransactionTest.php`) — validate accessor logic
7. **Feature tests** (`BankingTransactionResourceTest.php`) — validate UI rendering
8. **Run tests** — verify everything

## Files Not Touched

- `BankingTransactionResource.php` — no changes needed (table and form are configured via dedicated classes)
- `ListBankingTransactions.php` — the "Unmatched" tab (#5 in TODO) is a separate task; do not modify it here
- Any relation managers — unchanged
- Any migrations — existing index on `banking_transaction_id` is sufficient
- `CompoundPrice` / `PriceFormatter` — unchanged (we use raw float for the accessor)
