# Bill Month Anchoring & Pro-Rated Billing Plan

## Overview

Every `BillableItemInstance` gets a `bill_month` (1-12) that anchors its billing cycle. Today the cycle is counted relative to *when the instance was first invoiced*; after this change it is counted from a fixed month-of-year.

- **Monthly** (`bill_cycle_in_months = 1`): unchanged - every month is a bill month.
- **Quarterly / Annually**: the cycle is anchored to `bill_month`. Billing only happens in months where `(month - bill_month) % cycle == 0`. A member who starts mid-cycle is invoiced immediately for the *remainder* of the cycle, pro-rated.
- **Once**: unaffected - one-off items never create `BillableItemInstance` records (see `MemberObjectObserver`).

Canonical example: a yearly subscription (`bill_period = annually`, `bill_month = January`) started in August pays **5/12** of the annual price immediately (Aug-Dec), then everyone is billed the full annual amount in January.

The source of `bill_month` is the `BillableItem` template (the "subscription"). It is denormalized onto each `BillableItemInstance` at creation, exactly like `bill_cycle_in_months` is already denormalized from `bill_period`. Existing rows are backfilled to `1` (January).

---

## Core calculation

Given an instance with `start_date`, `bill_month` `m` (1-12) and `bill_cycle_in_months` `c`, and an invoice date `$when`:

```
offset    = ((month($when) - m + 12) % 12) % c
anchor    = firstOfMonth($when) - offset months    // most recent bill month <= $when
nextBill  = anchor + c months                      // next bill month
coverage  = max(firstOfMonth(start_date), anchor)
months    = clamp(nextBill - coverage, 1..c)
quantity  = months / c
```

- Monthly (`c == 1`) and one-off always return `1.0` (unit price is already the period price).
- `bill_month` has no effect on monthly items because `((month - m) % 12) % 1 == 0` always.

Worked examples (annual, `m = 1`):

| start month  | first batch month | anchor      | quantity |
|--------------|-------------------|-------------|----------|
| August (8)   | Aug or Sep        | Jan         | 5/12     |
| January (1)  | Jan               | Jan         | 1.0      |
| February (2) | Feb               | Jan         | 11/12    |
| December (12)| Jan (next year)   | Jan (next)  | 1.0 (Dec waived, whole-period billing) |

The billability filter ("already billed this window?") is: **exclude the instance if an invoice line already exists for the same member + item with `firstOfMonth(invoice.date) >= anchor`.**

---

## Database

### Migration 1: add `bill_month` to both tables

One migration file, date-stamped (e.g. `2026_08_14_000000_add_bill_month_to_billable_items_and_instances.php`):

```php
Schema::table('billable_items', static function (Blueprint $table) {
    $table->unsignedTinyInteger('bill_month')->default(1);
});

Schema::table('billable_item_instances', static function (Blueprint $table) {
    $table->unsignedTinyInteger('bill_month')->default(1);
});
```

- Column type matches existing `bill_cycle_in_months` (`unsignedTinyInteger`).
- `default(1)` backfills existing rows to January. A plain `migrate` is correct; `task artisan migrate:fresh --seed` also works (dev DB).
- `down()` drops both columns.

### Migration 2: increase `invoice_lines.quantity` precision

Pro-rated quantities are fractional (e.g. `5/12 = 0.416666...`). `decimal(10,2)` rounds `0.4167` to `0.42`, making a EUR 68 annual fee bill EUR 28.56 instead of the correct EUR 28.33. Increase precision so `price * quantity` stays accurate to the cent:

```php
Schema::table('invoice_lines', static function (Blueprint $table) {
    $table->decimal('quantity', 10, 6)->default(1)->change();
});
```

- Existing rows (`1.0`, `2.0`) are unaffected semantically.
- `down()` reverts to `decimal(10, 2)`.
- Note: `1/12` is a repeating decimal, so no finite precision is exact. `decimal(10,6)` keeps the monetary rounding error under a cent. If exactness is ever required, the alternative is `quantity = months` with a monthly unit price - out of scope.

---

## Models

### `app/Models/BillableItem.php`

- Add `bill_month` to `#[Fillable]`.
- Add `'bill_month' => 'integer'` to `casts()`.
- Add `'bill_month' => 1` to `createDefault()`'s default array.
- Change `toInvoiceBillableItem()` to accept an optional quantity:

```php
public function toInvoiceBillableItem(float $quantity = 1.0): InvoiceBillableItem
{
    return new InvoiceBillableItem(
        new BillableItemId($this->id),
        $this->compound_price,
        $quantity,
        $this->description,
        CostCenterId::create($this->cost_center_id),
    );
}
```

The default keeps `MemberObjectObserver` and its test working unchanged (one-off items -> quantity `1.0`).

### `app/Models/BillableItemInstance.php`

- Add `bill_month` to `#[Fillable]`.
- Add `'bill_month' => 'integer'` to `casts()`.
- Add `quantityFor()` (add imports `Carbon\CarbonImmutable` and `DateTimeInterface`):

```php
public function quantityFor(DateTimeInterface $when): float
{
    $cycle = (int) $this->bill_cycle_in_months;

    if ($cycle <= 1) {
        return 1.0;
    }

    $invoiceMonth = CarbonImmutable::create($when)->firstOfMonth();
    if ($this->start_date->greaterThanOrEqualTo($invoiceMonth)) {
        return 0.0;
    }
    
    $month = (int) $invoiceMonth->format('n');
    $billMonth = (int) $this->bill_month;

    $offset = (($month - $billMonth + 12) % 12) % $cycle;
    $anchor = $invoiceMonth->subMonths($offset);
    $nextBill = $anchor->addMonths($cycle);

    $months = max(1, min($cycle, $nextBill->diffInMonths($anchor)));

    return $months / $cycle;
}
```

---

## Infrastructure

### `app/Infrastructure/Invoices/Billing/BillableItemDbInstanceRepository.php`

Copy `bill_month` from the item when creating instances in both `add()` and `ensure()`:

```php
// add()
'bill_cycle_in_months' => $billableItem->bill_period->toBillPeriodInMonths(),
'bill_month' => $billableItem->bill_month,

// ensure() - firstOrCreate attributes
'bill_cycle_in_months' => $billableItem->bill_period->toBillPeriodInMonths(),
'bill_month' => $billableItem->bill_month,
```

### `app/Infrastructure/Invoices/Billing/BillableItemsViewDbRepository.php`

Two changes:

1. **Quantity** - in `listBillableItemsForMember()`, compute per-instance quantity:

```php
->map(static fn (BillableItemInstance $instance) => $instance->billableItem->toInvoiceBillableItem($instance->quantityFor($when)))
```

2. **Anchored window** - in `billableItemQuery()`, replace the `whereNotExists` cycle-window condition with the bill-month-anchored condition. Compute the invoice month once in PHP:

```php
$date = new Carbon($when)->firstOfMonth()->format('Y-m-d');
$month = (int) Carbon::create($when)->format('n');
```

Then inside the driver branch (change `>` to `>=` and anchor to `bill_month`):

**sqlite:**

```php
$query->whereRaw(
    "strftime('%Y-%m-01', invoices.date) >= date(?, '-' || (((? - billable_item_instances.bill_month + 12) % 12) % billable_item_instances.bill_cycle_in_months) || ' months')",
    [$date, $month],
);
```

**postgres:**

```php
$query->whereRaw(
    "DATE_TRUNC('month', invoices.date) >= '{$date}'::date - MAKE_INTERVAL(0, ((({$month} - billable_item_instances.bill_month + 12) % 12) % billable_item_instances.bill_cycle_in_months))",
);
```

`bill_month` is read from `billable_item_instances` (the denormalized instance column), matching how `bill_cycle_in_months` is already referenced.

---

## Factories

### `database/factories/BillableItemFactory.php`

Add `'bill_month' => 1` to `definition()` (fixed for determinism; tests override when needed).

### `database/factories/BillableItemInstanceFactory.php`

Add `'bill_month' => 1` to `definition()`.

---

## Seeders

No seeding changes required - `BillableItem::create()` calls in `MembershipSeeder`, `StorageSpaceLocationSeeder`, etc. rely on the DB default (`1`). If a seeder should use a non-January anchor, set `'bill_month'` explicitly (not required by this feature).

---

## Filament

### New: `app/Filament/Admin/Labels/BillMonthLabels.php`

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Labels;

final class BillMonthLabels
{
    /** @return array<int, string> */
    public static function options(): array
    {
        $options = [];

        for ($month = 1; $month <= 12; $month++) {
            $options[$month] = __('labels.months.' . $month);
        }

        return $options;
    }
}
```

### Forms - add a `bill_month` select next to every existing `bill_period` select

Add this component (import `BillMonthLabels`) to each billing section:

```php
Select::make('bill_month')
    ->label(__('labels.bill_month'))
    ->options(BillMonthLabels::options())
    ->default(1)
    ->required(),
```

Files to update:

- `app/Filament/Admin/Resources/Memberships/Schemas/MembershipForm.php` (adult AND kids billing sections)
- `app/Filament/Admin/Resources/Activities/Schemas/ActivityForm.php`
- `app/Filament/Admin/Resources/StorageSpaceLocations/Schemas/StorageSpaceLocationForm.php`
- `app/Filament/Admin/Resources/ExtraMembershipItems/Schemas/ExtraMembershipItemForm.php`
- `app/Filament/Admin/Resources/MemberObjectTypes/Schemas/MemberObjectTypeForm.php`

For nested-relationship forms, `bill_month` flows through the relationship save automatically (same as `bill_period`). No `mutateRelationshipDataBeforeCreateUsing` change needed.

### `app/Filament/Admin/Resources/Members/RelationManagers/BillableItemInstancesRelationManager.php`

Add a column showing the instance anchor month:

```php
TextColumn::make('bill_month')
    ->label(__('labels.bill_month'))
    ->formatStateUsing(static fn (int $state): string => __('labels.months.' . $state)),
```

---

## Language Labels

### `lang/nl/labels.php`

Add:

```php
'bill_month' => 'Factuurmaand',
'months' => [
    1 => 'Januari',
    2 => 'Februari',
    3 => 'Maart',
    4 => 'April',
    5 => 'Mei',
    6 => 'Juni',
    7 => 'Juli',
    8 => 'Augustus',
    9 => 'September',
    10 => 'Oktober',
    11 => 'November',
    12 => 'December',
],
```

---

## Tests

Run tests with `./Taskfile artisan test --compact` (specific file/filter where noted).

### New unit test: `tests/Unit/Domain/Invoices/Billing/BillableItemInstanceTest.php`

Test `quantityFor()` via `new BillableItemInstance([...])` (no DB needed; casts apply on attribute access). Cover:

1. Monthly (`bill_cycle_in_months = 1`, any `bill_month`) -> `1.0`.
2. Annual `bill_month=1`, start `2026-08-15`, when `2026-08-01` -> `5/12` (assert with delta).
3. Annual `bill_month=1`, start `2026-01-15`, when `2026-01-20` -> `1.0`.
4. Annual `bill_month=1`, start `2026-08-15`, when `2027-01-01` (next year) -> `1.0`.
5. Quarterly `bill_month=1`, start `2026-02-01`, when `2026-02-15` -> `2/3`.
6. Quarterly `bill_month=1`, start `2026-01-01`, when `2026-04-01` (bill month) -> `1.0`.
7. Annual `bill_month=7`, start `2026-08-01`, when `2026-08-01` -> `11/12`.
8. Start before the anchor (start `2026-01-01`, when `2026-08-01`, annual `bill_month=1`) -> `1.0`.

### Update: `tests/Feature/Infrastructure/Invoices/BillableItemsViewDbRepositoryTest.php`

Existing tests stay green (monthly default `bill_month=1`; the anchored condition reduces to the old monthly window). Add feature tests:

1. **Pro-rated quantity** - annual item, instance `bill_month=1`, `start_date=2026-08-01`, `bill_cycle_in_months=12`, `$when=2026-08-15`. Assert `listBillableItemsForMember` returns one item with `quantity` near `5/12`.
2. **Quarterly anchor** - quarterly item, `bill_month=1`, `start_date=2026-02-01`, `$when=2026-02-15`. Assert quantity near `2/3`.
3. **Anchored re-billing** - annual item `bill_month=1`, instance started `2026-08-01`, an invoice line dated `2026-08-05` exists. `$when=2026-09-15` returns no item (still same window); `$when=2027-01-15` returns the item with quantity `1.0`.
4. **Non-January bill month** - annual item `bill_month=7`, instance started `2026-08-01`, `$when=2026-08-15` returns quantity near `11/12`.

### Update: `tests/Feature/Infrastructure/Invoices/BillableItemDbInstanceRepositoryTest.php`

- `test_add_instance_creates_record_with_correct_bill_period`: also assert `bill_month` matches the item.
- `test_ensure_creates_record_when_missing`: assert `bill_month` copied.
- Add a case where the item has `bill_month=6` and assert the instance gets `bill_month=6`.

### No change expected in:

- `tests/Unit/Domain/Invoices/InvoiceGeneratorImplTest.php` and `InvoiceBatchGeneratorImplTest.php` - the `BillableItemsViewRepository` interface signature is unchanged; the domain `BillableItem` value object is unchanged.
- `tests/Feature/Observers/MemberObjectObserverTest.php` - `toInvoiceBillableItem()` default quantity `1.0` unchanged.
- `tests/Feature/Infrastructure/Invoices/InvoiceLineCostCenterPropagationTest.php` - monthly instance, quantity unaffected.

---

## Implementation Order

1. Migration 1 (add `bill_month` to both tables) + Migration 2 (quantity precision).
2. Update `BillableItem` model (Fillable, cast, `createDefault`, `toInvoiceBillableItem($quantity)`).
3. Update `BillableItemInstance` model (Fillable, cast, `quantityFor()`).
4. Update `BillableItemDbInstanceRepository` (`add` + `ensure` copy `bill_month`).
5. Update `BillableItemsViewDbRepository` (quantity mapping + anchored `whereNotExists`).
6. Update both factories.
7. Add `BillMonthLabels` + `lang/nl/labels.php` labels.
8. Add `bill_month` selects to the 5 forms + relation manager column.
9. Write unit test + feature tests.
10. Run `./Taskfile artisan migrate:fresh --seed` (dev) and the affected test files.

---

## Key Design Decisions

| Decision | Rationale |
|---|---|
| `bill_month` lives on `BillableItem` AND is denormalized to `BillableItemInstance` | The item is the editable template (the "subscription"); the instance is the source of truth at billing time. Mirrors how `bill_cycle_in_months` is denormalized from `bill_period`. |
| Default `bill_month = 1` (January) | Calendar-year membership year for a Dutch watersports association. Configurable per item in the forms. |
| Whole-period billing anchored at `bill_month` | Joining mid-cycle bills only the remainder; joining on/before the anchor bills the full upcoming period. Simplest rule that satisfies the August example. |
| Quantity = fraction of period (`months / cycle`) | Keeps `BillableItem.price` as the period price (unchanged). `InvoiceLine.subTotal = price * quantity` already computes the money. |
| `decimal(10,6)` quantity | Preserves pro-rated monetary accuracy to under a cent. |
| Anchored window expressed in SQL (both drivers) | Keeps the existing `whereNotExists` approach; `bill_month` read from the instance column. |

## Open Question / Assumption

- **December-joiner edge case** (join in the month immediately before `bill_month`): this plan bills them the full upcoming period at the next bill month (the pre-bill-month month is waived). If the association instead wants the pre-bill-month month pro-rated (1/12 in December), the quantity rule must be revisited. Confirm the desired behavior before implementation.
