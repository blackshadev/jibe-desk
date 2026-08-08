# Plan: Show Relations in BookkeepingRecord View

**Date**: 2026-08-08
**Goal**: Display related Invoice/PurchaseOrder and BankingTransaction in the BookkeepingRecord view page, with clickable links to navigate to those related records.

---

## Analysis Summary

### Current Data Model

`BookkeepingRecord` already has the relevant relationships:

| Relationship | Type | Related Model | Notes |
|---|---|---|---|
| `reference()` | `MorphTo` | `Invoice` or `PurchaseOrder` | Polymorphic, one record. Column: `reference_type` + `reference_id` |
| `bankingTransaction()` | `BelongsTo` | `BankingTransaction` | Nullable. Column: `banking_transaction_id` |

The inverse side (other models referencing BookkeepingRecord):

| Model | Relationship | Type |
|---|---|---|
| `Invoice` | `bookkeepingRecords()` | `MorphMany` |
| `PurchaseOrder` | `bookkeepingRecords()` | `MorphMany` |
| `BankingTransaction` | `bookkeepingRecords()` | `HasMany` |

Because `reference()` and `bankingTransaction()` are **single-record** relationships (not collections), standard Filament `RelationManager` classes won't work — those require `HasMany`/`MorphMany`/`BelongsToMany`.

### Current View Page Problem

`ViewBookkeepingRecord` (at `app/Filament/Admin/Resources/BookkeepingRecords/Pages/ViewBookkeepingRecord.php`) currently:
- Extends `EditRecord` (not `ViewRecord`) — shows a disabled form
- Has no `getRelationManagers()`, no `infolist()`, no relationship display
- Only shows a header `EditAction`

The table (`BookkeepingRecordsTable`) has a "Go to related" action, but the detail view shows nothing about relationships.

### Project Patterns

- `ViewPurchaseOrder` and `ViewBankingTransaction` extend `ViewRecord`, use `hasCombinedRelationManagerTabsWithContent()`, and define `getRelationManagers()` for collection-based relations.
- Form schemas follow a pattern of extracting to a dedicated class (e.g. `BookkeepingRecordForm`, `PurchaseOrderForm`).
- The project has no existing infolist schemas, so we'll introduce one.

### Approach

Use Filament's **infolist** to display the single-record relationships as clickable `TextEntry` entries. This is the canonical Filament way to show BelongsTo/MorphTo data in a ViewRecord.

---

## Implementation Steps

### Step 1: Create `BookkeepingRecordInfolist` schema class

**New file**: `app/Filament/Admin/Resources/BookkeepingRecords/Schemas/BookkeepingRecordInfolist.php`

This class follows the same pattern as `BookkeepingRecordForm`. It builds an infolist schema with:
- All existing form fields displayed as infolist entries (year, cost center, description, amount)
- A "Related Records" section containing:
  - **Reference** — a `TextEntry` that shows the related Invoice or PurchaseOrder as a clickable link. Uses `->url()` to generate the correct edit/view URL based on the morph type (same logic as the "Go to related" action in `BookkeepingRecordsTable`).
  - **Banking Transaction** — a `TextEntry` that shows the related BankingTransaction as a clickable link. Uses `->url()` pointing to `BankingTransactionResource`.

**Key references**:
- Use `ViewOrEdit::route()` util to generate correct URLs (respects policy for edit vs view).
- Use `BookkeepingRecordResource::class` and `BankingTransactionResource::class` for route generation.
- Use existing translation keys: `labels.goto_related`, `labels.reference`, `labels.banking_transaction`, `labels.invoice`, `labels.purchase_order`.
- Check if the morph reference is `Invoice` vs `PurchaseOrder` to build correct URLs (matching the `match()` logic in `BookkeepingRecordsTable` line 72-76).

**Imports needed**:
```php
use App\Filament\Admin\Resources\BankingTransactions\BankingTransactionResource;
use App\Filament\Admin\Resources\Invoices\InvoiceResource;
use App\Filament\Admin\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Filament\Admin\Utils\ViewOrEdit;
use App\Models\BookkeepingRecord;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
```

**Infolist entries mapping** (matching existing form fields):

| Entry | Source | Notes |
|---|---|---|
| `TextEntry::make('year')` | model attribute | Numeric book year |
| `TextEntry::make('costCenter.title')` | relationship dot notation | Cost center title |
| `TextEntry::make('description')` | model attribute | Description text |
| `TextEntry::make('amount')` | accessor | Use `->money('EUR')` |

Add a `Section` for related records containing:

```php
// reference: Invoice or PurchaseOrder
TextEntry::make('reference')
    ->label(__('labels.reference'))
    ->state(static fn (BookkeepingRecord $record): string => match (true) {
        $record->reference instanceof Invoice => $record->reference->display_name,
        $record->reference instanceof PurchaseOrder => $record->reference->display_name,
        default => '—',
    })
    ->url(static fn (BookkeepingRecord $record): ?string => match (true) {
        $record->reference instanceof Invoice => ViewOrEdit::routeFor(InvoiceResource::class, $record->reference),
        $record->reference instanceof PurchaseOrder => ViewOrEdit::routeFor(PurchaseOrderResource::class, $record->reference),
        default => null,
    })
    ->visible(static fn (BookkeepingRecord $record): bool => $record->reference !== null),

// bankingTransaction
TextEntry::make('bankingTransaction')
    ->label(__('labels.banking_transaction'))
    ->state(static fn (BookkeepingRecord $record): string => $record->bankingTransaction
        ? sprintf('[%s] %s', $record->bankingTransaction->date->format('Y-m-d'), $record->bankingTransaction->description)
        : '—')
    ->url(static fn (BookkeepingRecord $record): ?string => $record->bankingTransaction
        ? ViewOrEdit::routeFor(BankingTransactionResource::class, $record->bankingTransaction)
        : null)
    ->visible(static fn (BookkeepingRecord $record): bool => $record->bankingTransaction !== null),
```

Both entries should use `->icon('heroicon-o-arrow-top-right-on-square')` or similar to indicate they're clickable links (consistent with the table's "Go to related" action).

### Step 2: Update `BookkeepingRecordResource` — add `infolist()` method

**File**: `app/Filament/Admin/Resources/BookkeepingRecords/BookkeepingRecordResource.php`

Add an `infolist()` method that delegates to `BookkeepingRecordInfolist::configure()`:

```php
use App\Filament\Admin\Resources\BookkeepingRecords\Schemas\BookkeepingRecordInfolist;
use Filament\Infolists\Infolist as InfolistSchema;

#[Override]
public static function infolist(InfolistSchema $infolist): InfolistSchema
{
    return BookkeepingRecordInfolist::configure($infolist);
}
```

Note: The type hint uses `Filament\Schemas\Schema` (same namespace as the form), but in Filament v5, `infolist()` receives a `Filament\Schemas\Schema`. Check the actual type used by `ViewRecord::infolist()` in the parent class — it may be just `Schema`. The method signature should match the parent's `infolist(Schema $schema): Schema`.

If we want the infolist only on the view page (not on the resource level), we can define it on `ViewBookkeepingRecord` instead. But defining it at the resource level is cleaner and follows Filament conventions.

### Step 3: Convert `ViewBookkeepingRecord` to extend `ViewRecord`

**File**: `app/Filament/Admin/Resources/BookkeepingRecords/Pages/ViewBookkeepingRecord.php`

Changes:
1. Change parent class from `EditRecord` to `ViewRecord`
2. Add `hasCombinedRelationManagerTabsWithContent()` returning `true` (matching ViewPurchaseOrder pattern)
3. Add `getContentTabLabel()` returning `__('labels.bookkeeping_record')`
4. Keep the existing `EditAction` in `getHeaderActions()`

```php
use Filament\Resources\Pages\ViewRecord;

class ViewBookkeepingRecord extends ViewRecord
{
    #[Override]
    protected static string $resource = BookkeepingRecordResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }

    #[Override]
    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    #[Override]
    public function getContentTabLabel(): string
    {
        return __('labels.bookkeeping_record');
    }
}
```

Note: `ViewRecord` will automatically use the resource's `infolist()` method (defined in Step 2). If the resource doesn't define `infolist()`, it falls back to displaying the form in disabled mode.

### Step 4: Eager-load relationships in the view

Since `ViewRecord` loads the record and its relationships need to be accessed in the infolist, ensure proper eager-loading. The infolist entries use `$record->reference` and `$record->bankingTransaction`, so these should be loaded.

Options:
- **Option A**: In `ViewBookkeepingRecord`, override `getQuery()` or `mutateFormDataBeforeFill()` — but with infolist, there's no form fill. Instead, we could use `$this->record` and ensure relations are loaded.
- **Option B**: In the infolist entries, use `->url()` with a closure — Filament will lazy-load the relationship when the closure accesses it. This is fine for a detail page.
- **Option C**: Override `mount()` in the page to eager-load: `$this->record->load(['reference', 'bankingTransaction'])`.

**Recommended**: Option B (lazy loading) is sufficient for a detail page with single-record relationships. No N+1 risk on a single-record view.

### Step 5: Update tests

**Test files to check/update**:
- Search for existing view page tests for BookkeepingRecord
- If none exist, create a feature test: `tests/Feature/Filament/BookkeepingRecords/ViewBookkeepingRecordTest.php`

The test should:
1. Create a `BookkeepingRecord` with a `reference` (Invoice or PurchaseOrder) and a `bankingTransaction`
2. Navigate to the view page
3. Assert the related records are displayed with correct links

### Step 6: Verify

- Run `./Taskfile artisan test --filter=BookkeepingRecord` to verify no regressions
- Run the test suite for the changed files

---

## Files Changed Summary

| File | Action | Description |
|---|---|---|
| `app/Filament/Admin/Resources/BookkeepingRecords/Schemas/BookkeepingRecordInfolist.php` | **Create** | New infolist schema class |
| `app/Filament/Admin/Resources/BookkeepingRecords/BookkeepingRecordResource.php` | **Modify** | Add `infolist()` method |
| `app/Filament/Admin/Resources/BookkeepingRecords/Pages/ViewBookkeepingRecord.php` | **Modify** | Extend `ViewRecord`, add tab methods |
| Test file(s) | **Create/Modify** | Feature test for view page |

---

## Risks & Notes

1. **Filament v5 API**: Verify the exact type hints for `infolist()` on `Resource` and `ViewRecord` classes. May differ from `Schema` type used in `form()`.
2. **Clickable links**: `TextEntry::url()` renders as `<a href>`. Use `->openUrlInNewTab()` if preferred.
3. **Null safety**: Both `reference` and `bankingTransaction` can be null — entries use `->visible()` to hide when null.
4. **display_name accessor**: Both `Invoice` and `PurchaseOrder` have `displayName` accessor. Confirm it's accessible as `->display_name` (Laravel magic accessor via snake_case).
