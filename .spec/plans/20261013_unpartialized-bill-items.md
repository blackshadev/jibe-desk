# Unpartialized Bill Items (Non-Prorated Volunteer Items)

**Date:** 2026-10-13

## Overview

Some billable items must never have their quantity prorated. Today every annually- or quarterly-billed item is automatically prorated to a fractional quantity when a member joins mid-cycle (for example `5/12 ≈ 0.42`). The volunteer contribution (`vrijwilligers_bijdrage`) and volunteer restitution (`vrijwilliger_restitutie`) should instead always bill at a full quantity of `1.00`, while the membership item keeps prorating.

This is achieved by adding a `prorate` boolean to the `BillableItem` template (default `true`). When `false`, the item's quantity is always `1.00` once it becomes active.

## Current Situation

The proration mechanism lives in `app/Models/BillableItemInstance::quantityFor()` (`quantityFor(DateTimeInterface $when): float`). It returns a fractional quantity whenever `bill_cycle_in_months > 1`:

- `bill_cycle_in_months <= 1` (monthly/once) returns `1.0`.
- `start_date` in a future month returns `0.0`.
- Otherwise it computes `months / cycle`, where `months` is the covered fraction of the bill period (e.g. a member starting in August on an annual item anchored to January yields `5/12`).

The fractional quantity becomes the `InvoiceLine.quantity` via `app/Infrastructure/Billing/BillableItemsViewDbRepository::listBillableItemsForMember()`, which maps each active `BillableItemInstance` to `$instance->billableItem->toInvoiceBillableItem($instance->quantityFor($when))`. The line subtotal is `price * quantity`, so a full quantity of `1.00` means the full period price is billed.

The two volunteer items are seeded in `database/seeders/MembershipSeeder.php` with `bill_period => BillPeriod::Annually` (lines 62 and 77), so they are currently prorated. This is confirmed by `tests/Feature/Scenarios/WindsurferLifetimeScenarioTest.php`, which asserts `Vrijwilligersbijdrage` at `0.42` (line 64) and `Vrijwilligersbijdrage restitutie` at `0.33` (line 97).

The item code enum `App\Domain\Members\ExtraMembershipItemCode` uses the singular strings `vrijwilligers_bijdrage` and `vrijwilliger_restitutie` (the todo's "vrijwilligers_bijdragen"/"vrijwilligers_restitutie" refer to the same items). No code strings are changed.

Key facts relevant to the design:

- `quantityFor()` only reads the denormalized `bill_cycle_in_months` and `bill_month` columns; it has no notion of item kind.
- The production caller (`BillableItemsViewDbRepository`) already eager-loads `billableItem` (`->with('billableItem')`).
- `bill_month` and `bill_cycle_in_months` are denormalized (frozen) onto the instance at creation, but the new `prorate` flag will be read live from the relation.
- `BillableItemSection` (`app/Filament/Admin/Sections/BillableItemSection.php`) is a shared form component used by MembershipForm, ActivityForm, StorageSpaceLocationForm, ExtraMembershipItemForm, and MemberObjectTypeForm.
- The codebase's established term for this concept is "pro-rated" / "proration" (see `.spec/plans/20260814_bill-month-proration.md`); the todo says "partialized".

## Planned Changes

Add a `prorate` boolean column to the `billable_items` table (default `true`), expose it on the `BillableItem` model and factory, short-circuit `quantityFor()` to return `1.0` when the item is not prorated, seed the two volunteer items with `prorate = false`, add an admin toggle, and update/add tests. The flag is read live from the `billableItem` relation with a null-safe default of `true`.

---

## Change: Database migration

Add a migration that appends `prorate` to `billable_items`. Existing rows are backfilled to `true` by the column default, so a plain `migrate` is correct and preserves current behavior for every existing item. No instance column is added (the flag is read from the relation, not denormalized).

New file `database/migrations/2026_10_13_000000_add_prorate_to_billable_items_table.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    public function up(): void
    {
        Schema::table('billable_items', static function (Blueprint $table): void {
            $table->boolean('prorate')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('billable_items', static function (Blueprint $table): void {
            $table->dropColumn('prorate');
        });
    }
};
```

---

## Change: `BillableItem` model

Add `prorate` to the fillable attributes, the casts, and the default factory-helper array.

File `app/Models/BillableItem.php`.

The `#[Fillable]` attribute becomes:

```php
#[Fillable(['description', 'price', 'vat', 'bill_period', 'bill_month', 'prorate', 'cost_center_id'])]
```

The `casts()` method gains a boolean cast:

```php
protected function casts(): array
{
    return [
        'bill_period' => BillPeriod::class,
        'bill_month' => 'integer',
        'prorate' => 'boolean',
        'price' => 'decimal:2',
        'vat' => 'decimal:2',
    ];
}
```

The `createDefault()` array gains the default (the DB default already covers persisted rows; this keeps the helper explicit):

```php
public static function createDefault(array $data = []): self
{
    return self::create([
        'description' => '',
        'price' => 0,
        'vat' => 0,
        'bill_period' => BillPeriod::Annually,
        'bill_month' => 1,
        'prorate' => true,
        'cost_center_id' => CostCenter::factory()->create()->id,
        ...$data,
    ]);
}
```

---

## Change: `BillableItemInstance` model — non-prorated short-circuit

In `quantityFor()`, after the "not yet started" guard and before the partial-quantity calculation, return `1.0` when the item is not prorated. The relation is read live with a null-safe fallback to `true` (prorate), so the existing bare-instance unit test and any path without a loaded relation keep today's prorated behavior.

File `app/Models/BillableItemInstance.php`.

```php
public function quantityFor(DateTimeInterface $when): float
{
    $cycle = (int) $this->bill_cycle_in_months;

    if ($cycle <= 1) {
        return 1.0;
    }

    if ($this->start_date->startOfMonth()->gt($when)) {
        return 0.0;
    }

    if (! ($this->billableItem?->prorate ?? true)) {
        return 1.0;
    }

    $invoiceMonth = CarbonImmutable::create($when)->firstOfMonth();

    $month = (int) $invoiceMonth->format('n');
    $billMonth = (int) $this->bill_month;

    $offset = ($month - $billMonth + 12) % 12 % $cycle;
    $anchor = $invoiceMonth->subMonths($offset);
    $nextBill = $anchor->addMonths($cycle);

    $coverage = $anchor->max($this->start_date->firstOfMonth());
    $months = max(1, min($cycle, abs($nextBill->diffInMonths($coverage))));

    return $months / $cycle;
}
```

Placement matters: the `prorate` check sits after the `start_date` guard, so a non-prorated item still returns `0.0` before its start date, then `1.0` for every subsequent invoice it appears on (appearance is unchanged — still anchored annually by `bill_month`).

---

## Change: `BillableItemFactory`

Add the default so newly created items prorate by default (tests override when needed).

File `database/factories/BillableItemFactory.php`, in `definition()`:

```php
return [
    'description' => fake()->sentence(),
    'price' => $price,
    'vat' => $price * 0.21,
    'bill_period' => fake()->randomElement(BillPeriod::cases())->value,
    'bill_month' => 1,
    'prorate' => true,
    'cost_center_id' => CostCenter::factory(),
];
```

---

## Change: `MembershipSeeder` — volunteer items become non-prorated

Set `prorate => false` on the two volunteer `BillableItem` factory states. The same-household discount items keep the default (`prorate = true`).

File `database/seeders/MembershipSeeder.php`.

Volunteer contribution (around line 56):

```php
ExtraMembershipItem::factory()
    ->for(
        BillableItem::factory()
            ->state([
                'price' => 20,
                'vat' => 4.2,
                'bill_period' => BillPeriod::Annually,
                'prorate' => false,
                'description' => 'Vrijwilligersbijdrage',
            ])
            ->for($costCenter),
    )
    ->create([
        'code' => ExtraMembershipItemCode::VolunteerContribution,
    ]);
```

Volunteer restitution (around line 71):

```php
ExtraMembershipItem::factory()
    ->for(
        BillableItem::factory()
            ->state([
                'price' => -22,
                'vat' => -4.62,
                'bill_period' => BillPeriod::Annually,
                'prorate' => false,
                'description' => 'Vrijwilligersbijdrage restitutie',
            ])
            ->for($costCenter),
    )
    ->create([
        'code' => ExtraMembershipItemCode::VolunteerRestitution,
    ]);
```

The same-household discount blocks (lines 86-114) are left unchanged.

---

## Change: Filament admin toggle

Add a toggle to the shared billing section so admins can mark any item as non-prorated. This surfaces the flag on every form that embeds `BillableItemSection` (memberships, activities, storage, extra membership items, member object types).

File `app/Filament/Admin/Sections/BillableItemSection.php`.

Add the `Toggle` import and the field:

```php
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
```

Insert after the `bill_month` select:

```php
Toggle::make('prorate')
    ->label(__('labels.prorate'))
    ->helperText(__('labels.prorate_hint'))
    ->default(true),
```

The `prorate` value flows through the existing relationship save automatically (same as `bill_period`/`bill_month`); no `mutateRelationshipDataBeforeCreateUsing`/`mutateRelationshipDataBeforeSaveUsing` change is needed.

---

## Change: Language labels

Add the Dutch label and helper text.

File `lang/nl/labels.php`, near the other billing labels (after `'bill_month'`):

```php
'prorate' => 'Naar rato factureren',
'prorate_hint' => 'Uitzetten om altijd de volledige periode (hoeveelheid 1.00) te factureren',
```

---

## Change: Tests

### Update `tests/Feature/Scenarios/WindsurferLifetimeScenarioTest.php`

The volunteer contribution and restitution are now full-quantity. Update the two assertions and the surrounding comments.

Step 3 (around lines 57-65) — membership stays prorated at `0.42`, the contribution becomes `1.0`:

```php
// Step 3 — first invoice: membership is pro-rated (5/12 ≈ 0.42); the contribution is full-quantity (1.0); activity is monthly.
$this->generateInvoiceFor($member);

$firstInvoice = $this->invoiceFor($member, '2026-08-15');
static::assertSame(3, $firstInvoice->lines()->count());
$this->assertInvoiceLine($firstInvoice, 'Lidmaatschap Windsurfer (volwassenen)', 68.00, 0.42);
$this->assertInvoiceLine($firstInvoice, 'Vrijwilligersbijdrage', 20.00, 1.0);
$this->assertInvoiceLine($firstInvoice, 'Activiteit: Reguliere les volwassenen', 37.00, 1.0);
```

Step 6 (around lines 90-97) — the restitution becomes full-quantity:

```php
// Step 6 — third invoice (next month): activity again + full-quantity restitution (1.0).
$this->travelToDate('2026-10-15');
$this->generateInvoiceFor($member);

$thirdInvoice = $this->invoiceFor($member, '2026-10-15');
static::assertSame(2, $thirdInvoice->lines()->count());
$this->assertInvoiceLine($thirdInvoice, 'Activiteit: Reguliere les volwassenen', 37.00, 1.0);
$this->assertInvoiceLine($thirdInvoice, 'Vrijwilligersbijdrage restitutie', -22.00, 1.0);
```

Step 7 (line 99-107) is already full-quantity (`1.0`) and needs no change.

### Add a focused non-prorated `quantityFor` test

Add a feature test to `tests/Feature/Infrastructure/Billing/BillableItemInstanceTest.php` that creates a real `BillableItem` with `prorate => false` and asserts `quantityFor()` returns `1.0` for an annual item started mid-year.

Imports to add:

```php
use App\Domain\Billing\BillPeriod;
use App\Models\BillableItem;
```

New test:

```php
public function test_quantity_for_returns_full_quantity_when_item_is_not_prorated(): void
{
    $billableItem = BillableItem::factory()->create([
        'bill_period' => BillPeriod::Annually,
        'prorate' => false,
    ]);

    $instance = BillableItemInstance::factory()->create([
        'billable_item_id' => $billableItem->id,
        'bill_cycle_in_months' => 12,
        'bill_month' => 1,
        'start_date' => '2026-08-15',
    ]);

    $quantity = $instance->load('billableItem')->quantityFor(new DateTimeImmutable('2026-08-15'));

    static::assertSame(1.0, $quantity);
}
```

### Optional edge coverage

A companion assertion that a non-prorated item started in the future still returns `0.0`:

```php
public function test_quantity_for_returns_zero_before_start_even_when_not_prorated(): void
{
    $billableItem = BillableItem::factory()->create([
        'bill_period' => BillPeriod::Annually,
        'prorate' => false,
    ]);

    $instance = BillableItemInstance::factory()->create([
        'billable_item_id' => $billableItem->id,
        'bill_cycle_in_months' => 12,
        'bill_month' => 1,
        'start_date' => '2027-01-01',
    ]);

    $quantity = $instance->load('billableItem')->quantityFor(new DateTimeImmutable('2026-08-01'));

    static::assertSame(0.0, $quantity);
}
```

---

## Change: Domain documentation

Add the `prorate` term to the Billing context glossary so the concept is named consistently (the code comments currently use "pro-rated" ad hoc).

File `.agents/docs/billing/CONTEXT.md`, under `## Language`:

```markdown
**Prorate**:
Whether a BillableItem's quantity is reduced to a fraction of the bill period when a member joins mid-cycle. When `true` (the default) an annual item started in August bills `5/12`; when `false` it always bills full quantity (`1.00`). Owned by Billing; the resulting quantity is consumed by Invoicing as `InvoiceLine.quantity`.
_Avoid_: partialize, pro-rate amount
```

---

## Decisions

| Decision | Rationale |
|---|---|
| Flag is a `prorate` boolean, default `true` | `false` means "never prorate"; matches the codebase's existing "pro-rated"/"proration" terminology. |
| Scope is only the two volunteer items | Same-household discounts keep prorating until explicitly changed. |
| Flag lives on `billable_items` only, read live from the relation | A template property; editing it affects existing instances at next generation. `bill_month`/`bill_cycle_in_months` remain frozen (denormalized) — accepted as a deliberate difference. |
| Null-safe fallback `$this->billableItem?->prorate ?? true` | Missing relation defaults to prorating, preserving current behavior and keeping the existing bare-instance unit test green. |
| Short-circuit placed after the `start_date` guard | Non-prorated items still return `0.0` before their start date, then `1.0` once active. |
| No change to `billableItemQuery` appearance SQL | Appearance (annual anchoring) is unchanged; only the quantity is affected. |

## Verification

- `./Taskfile artisan test --compact tests/Feature/Scenarios/WindsurferLifetimeScenarioTest.php`
- `./Taskfile artisan test --compact tests/Feature/Infrastructure/Billing/BillableItemInstanceTest.php`
- `./Taskfile artisan migrate` (or `migrate:fresh --seed` for dev)
