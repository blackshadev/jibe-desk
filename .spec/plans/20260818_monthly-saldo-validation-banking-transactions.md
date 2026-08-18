# Plan: Monthly Banking Saldo Validation (Group by Month + Sum)

**Date**: 2026-08-18
**Goal**: In the bookkeeping section, give financial administration a way to validate the actual bank saldo each month against the expected saldo from bookkeeping. The concrete, agreed first step is to group the banking transactions table by month and show a summary that sums the `amount` column.

---

## Analysis Summary

### Current state

`app/Filament/Admin/Resources/BankingTransactions/Tables/BankingTransactionsTable.php` renders the flat list of `BankingTransaction` rows. It has:

- Columns: `date`, `description`, `amount` (money EUR, colored by sign), `unmatched_amount`, `status`, `resolve_status`, `banking_account_number`, `reversedByTransaction`, `created_at`.
- A `book_year` filter (driver-aware `EXTRACT(YEAR …)` for pgsql vs `STRFTIME('%Y', …)` for sqlite) and an `is_reversal` filter.
- No grouping and no column summaries anywhere in the codebase (this is the first use of `summarize()`).

`BankingTransaction` (model) casts `date` to a Carbon date (`casts()` → `'date' => 'date'`) and stores `amount` as a decimal (10,3). The `date` column is indexed. There is no stored month column.

### The reconciliation goal vs. what the data allows

The bank side is fully available: imported bank statement lines have a `date` and an `amount`. Summing `amount` per month yields the **actual monthly bank movement** (net inflow/outflow), which is what the requested grouping + summary produces.

The "expected saldo based on bookkeeping" side is **not available at month granularity today**. `BookkeepingRecord` stores a `year`, `amount_price`, `amount_vat`, an optional polymorphic `reference` (Invoice/PurchaseOrder), and an optional `banking_transaction_id` (nullable FK to `BankingTransaction`, which has a `date`). There is no month column and no per-month bookkeeping total.

**Scope decision**: This plan implements exactly the requested change — month grouping + `amount` sum on the banking transactions table — which directly enables manual validation. It deliberately does **not** build an "expected monthly saldo" from bookkeeping, because that requires product input on the definition (opening balance + expected flows? sum of matched bookkeeping in the month? VAT included?). See *Open Questions*.

### Filament v5 mechanics (verified against 5.x source)

- Table grouping is **in-memory by group key** (`Group::getKey`/`getStringKey`), with the query **ordered** via `Group::orderQuery`. Records are grouped into headings when the key changes, so records must be ordered by month.
- Column summaries for **groups** run a SQL query whose `GROUP BY` comes from `Group::groupQuery`; the summary select reads `$query->groups[0]`.
- `Group::scopeQueryByKey` scopes a query to a single group (used for scoped queries/features).

Because there is no real `month` column, the default `Group` behavior (`groupBy('month')`, `orderBy('month')`, `where('month', $key)`) would produce invalid SQL. All three hooks must be overridden with a driver-aware raw month expression (same pattern as the existing `book_year` filter).

The `Group` hooks receive **different builder types**:
- `groupQueryUsing` → `Illuminate\Database\Query\Builder`
- `orderQueryUsing`, `scopeQueryByKeyUsing` → `Illuminate\Database\Eloquent\Builder`

---

## Implementation Steps

### Step 1: Add month grouping + amount sum to `BankingTransactionsTable`

**File**: `app/Filament/Admin/Resources/BankingTransactions/Tables/BankingTransactionsTable.php`

1. Add imports:
   ```php
   use Filament\Tables\Columns\Summarizers\Sum;
   use Filament\Tables\Grouping\Group;
   use Illuminate\Database\Query\Builder as QueryBuilder;
   ```
   (`Illuminate\Database\Eloquent\Builder` is already imported; keep it for the filter closures and the `orderQueryUsing`/`scopeQueryByKeyUsing` hooks.)

2. Add a `Sum` summarizer to the `amount` column (it is not the first column, so it may carry summarizers — the `date` column cannot):

   ```php
   TextColumn::make('amount')
       ->label(__('labels.price'))
       ->money('EUR')
       ->sortable()
       ->alignEnd()
       ->color(static fn (BankingTransaction $record): string => $record->amount < 0 ? 'danger' : 'success')
       ->summarize(
           Sum::make('amount_sum')
               ->label(__('labels.total_amount'))
               ->money('EUR'),
       ),
   ```

   - Give the summarizer a stable id (`amount_sum`) so tests can assert it via `assertTableColumnSummarySet('amount', 'amount_sum', …)`.
   - `Sum` inherits `CanFormatState`, so `->money('EUR')` matches the column's display. If it turns out not to be supported on the summarizer, fall back to `->formatStateUsing(static fn (float $state): string => PriceFormatter::format($state))` (the codebase already uses `App\Domain\Invoices\Formatters\PriceFormatter` in `BankingTransactionStats`).

3. Add the month `Group` and enable it by default. Compute the driver-aware month expression once before building the table:

   ```php
   public static function configure(Table $table): Table
   {
       $monthExpression = self::monthExpression();

       return $table
           ->columns([ /* …existing columns + summarized amount… */ ])
           ->groups([
               Group::make('month')
                   ->label(__('labels.month'))
                   ->getKeyFromRecordUsing(static fn (BankingTransaction $record): string => $record->date->format('Y-m'))
                   ->getTitleFromRecordUsing(static fn (BankingTransaction $record): string => $record->date->format('Y-m'))
                   ->groupQueryUsing(static fn (QueryBuilder $query): QueryBuilder => $query->groupByRaw($monthExpression))
                   ->orderQueryUsing(static fn (Builder $query, string $direction): Builder => $query->orderBy('date', $direction))
                   ->scopeQueryByKeyUsing(static fn (Builder $query, ?string $key): Builder => $key === null
                       ? $query
                       : $query->whereRaw($monthExpression . ' = ?', [$key])),
           ])
           ->defaultGroup('month')
           ->groupingSettingsHidden()
           ->toolbarActions([ /* …unchanged… */ ])
           ->filters([ /* …unchanged… */ ])
           ->filtersLayout(FiltersLayout::BeforeContent);
   }
   ```

4. Add the private helper (placed after `configure()`):

   ```php
   /**
    * Raw SQL expression that returns the month (`YYYY-MM`) of the transaction date,
    * matching the `Y-m` format produced by `getKeyFromRecordUsing`.
    */
   private static function monthExpression(): string
   {
       return DB::connection()->getConfig()['driver'] === 'pgsql'
           ? "to_char(date, 'YYYY-MM')"
           : "strftime('%Y-%m', date)";
   }
   ```

   This mirrors the existing driver branch in the `book_year` filter (`EXTRACT`/`STRFTIME`). `to_char(date, 'YYYY-MM')` (pgsql) and `strftime('%Y-%m', date)` (sqlite) both produce `2026-08`, matching `$record->date->format('Y-m')`.

Notes on the hooks:
- `orderQueryUsing` orders by the real `date` column (chronological = month order) rather than a raw expression — simpler and driver-agnostic, and keeps records within a month contiguous so grouping renders correctly.
- `groupingSettingsHidden()` locks the table to month grouping (only one meaningful group). Optional: leave it out if admins should be allowed to disable grouping.
- Do **not** use `groupsOnly()` — admins still need to see the individual transactions, only the monthly totals are added.
- `collapsible()` is optional if the month groups get long; default (expanded) is fine for now.

### Step 2: Add the translation label

**File**: `lang/nl/labels.php`

Add a `month` key (place near `date` / the banking labels block):

```php
'month' => 'Maand',
```

Reuse the existing `total_amount` (`'Totaal'`) key for the sum label — no new key needed.

### Step 3: Tests

**File**: `tests/Feature/Filament/BankingTransaction/BankingTransactionResourceTest.php` (extend) — or a new test file in the same directory.

Add a feature test covering happy path + the month grouping:

```php
public function test_banking_transactions_are_grouped_by_month_with_amount_summary(): void
{
    $this->withAuthorizedUser();

    BankingTransaction::factory()->create(['date' => '2026-01-10', 'amount' => 100.00]);
    BankingTransaction::factory()->create(['date' => '2026-01-20', 'amount' => -30.00]);
    BankingTransaction::factory()->create(['date' => '2026-02-05', 'amount' => 50.00]);

    Livewire::test(ListBankingTransactions::class)
        ->assertSuccessful()
        ->assertSee('2026-01')       // group heading rendered
        ->assertSee('2026-02')
        ->assertTableColumnSummarySet('amount', 'amount_sum', 120.00);
}
```

- Total = `100.00 - 30.00 + 50.00 = 120.00`.
- `assertTableColumnSummarySet('amount', 'amount_sum', …)` asserts the all-table total summary (per the Filament testing docs). Per-group sums are not directly assertable with the documented helper — verify those in the UI, or add a targeted assertion against the rendered HTML if needed.
- Also keep the existing list-page tests passing (`test_list_page_is_accessible`, `test_can_list_banking_transactions`).

If a failure-path/edge-case test is warranted: create transactions with a mix of positive/negative amounts and a reversal transaction, and assert the total summary still equals the plain `sum('amount')` of all rows.

### Step 4: Verify

```bash
./Taskfile artisan test --filter=BankingTransactionResourceTest --compact
```

Then ask the user whether to run the full suite (`./Taskfile artisan test --compact`).

Manually confirm in the UI: the banking transactions list now shows month headings (e.g. "Maand 2026-08") with a "Totaal" row per group and a grand total, and that the per-month totals are correct with negative amounts and reversals.

---

## Files Changed Summary

| File | Action | Description |
|---|---|---|
| `app/Filament/Admin/Resources/BankingTransactions/Tables/BankingTransactionsTable.php` | **Modify** | Add `Sum` summarizer on `amount`; add month `Group` + `defaultGroup('month')` + `groupingSettingsHidden()`; add `monthExpression()` helper; add imports |
| `lang/nl/labels.php` | **Modify** | Add `'month' => 'Maand'` |
| `tests/Feature/Filament/BankingTransaction/BankingTransactionResourceTest.php` | **Modify** | Add grouping + summary feature test |

No new models, migrations, or resources. No dependency changes.

---

## Risks & Notes

1. **Builder type mismatch**: `groupQueryUsing` receives `Illuminate\Database\Query\Builder`; `orderQueryUsing`/`scopeQueryByKeyUsing` receive `Illuminate\Database\Eloquent\Builder`. Hinting the wrong type will throw a `TypeError` at runtime (Filament injects by parameter name). Use the aliases exactly as shown.
2. **First column can't summarize**: `date` is the first column and must not carry a summarizer. Only `amount` gets `Sum`.
3. **Cross-DB**: keep the `pgsql`/`sqlite` branch in `monthExpression()`. The group key format (`Y-m`) must exactly match what the SQL expression produces, or `scopeQueryByKeyUsing`/group summaries will mismatch.
4. **Sum money formatting**: confirm `Sum::make()->money('EUR')` is supported on the summarizer in this Filament version; otherwise use `formatStateUsing(PriceFormatter::format(...))`.
5. **Group title**: `getTitleFromRecordUsing` returns `Y-m` (deterministic, test-friendly). Dutch month names (`translatedFormat('F Y')`) would require Carbon's locale to be set (`Carbon::setLocale('nl')`), which is currently not configured — avoid for now.
6. **Group settings UI**: `groupingSettingsHidden()` is recommended (month is the only meaningful group). Remove if admins should be able to turn grouping off.
7. **Pagination + summary**: by default Filament renders page, group, and all-table summaries. With the default pagination this is fine; if the all-table total is redundant, it can be suppressed with `->summaries(allTableCondition: false)` later.

---

## Open Questions (for product/lead — no code blocked)

1. **Definition of "expected saldo from bookkeeping"**: Is it an opening balance + expected flows, or the sum of bookkeeping records matched to that month, or something else? `BookkeepingRecord` has no month; deriving one would require joining through `banking_transaction_id` (which is nullable — invoice/PO-sourced records without a linked bank transaction would be excluded). Confirm the definition before a follow-up.
2. **Scope of the validation**: Is the monthly bank sum (this change) sufficient for the reconciliation workflow, or is a dedicated reconciliation page comparing bank vs. bookkeeping per month required?
3. **VAT**: Should the expected saldo include VAT (`amount_vat`) or only `amount_price`? The bank `amount` is gross; bookkeeping splits price/VAT.
