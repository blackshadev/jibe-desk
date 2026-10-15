# Implementation Plan: Registration Fee ("Inschrijfgeld") on the First Invoice

**Date:** Tue Oct 13 2026

## Overview

After a member is registered and accepted, a one-time registration fee ("Inschrijfgeld") must be added to their **first invoice**. The fee is managed as an `ExtraMembershipItem` — a `code` mapped to a `BillableItem` — so it can be edited through the existing `ExtraMembershipItems` admin resource. Billing applicators resolve the fee's `BillableItemId` via `ExtraMembershipBillingItemRepository::getByCode(...)` and add a `BillableItemInstance` at acceptance; the existing invoice-generation engine turns that active instance into an `InvoiceLine` on the next invoice after acceptance.

The fee is **€10 excl. VAT (21% → €2.10 VAT)**, flat for all ages, applied to **every newly accepted member** across **all tiers** (including `Bestuurslid` and `Donateur`), and for **both** acceptance paths (public registration and admin-created members).

## Summary of current situation

- `ExtraMembershipItem` (`app/Models/ExtraMembershipItem.php`) is a mapping table (`extra_membership_items`) with `billable_item_id` and a `code` cast to `App\Domain\Members\ExtraMembershipItemCode`. The enum today has four cases: `VolunteerRestitution`, `VolunteerContribution`, `SameHouseholdDiscountYoungster`, `SameHouseholdDiscountAdult`.
- Billing applicators in `app/Domain/Billing/BillingItemApplicators/` (`ApplyMembershipBillingImpl`, `ApplyMemberVolunteerBillingImpl`, `ApplySameHouseholdBillingImpl`, …) resolve `BillableItemId` by code via `App\Domain\Members\ExtraMembershipBillingItemRepository` (port; `App\Infrastructure\Billing\ExtraMembershipBillingItemDbRepository` implementation) and manage `BillableItemInstance` rows through `App\Domain\Billing\BillableItemInstanceRepository`.
- Membership acceptance happens two ways, both discovered/registered already:
  - **Public registration** → admin calls `AcceptMemberService::accept()` → dispatches `MemberAccepted` → `ApplyMemberAcceptedBilling` listener (`app/Domain/Members/Listeners/ApplyMemberAcceptedBilling.php`) applies membership/volunteer/household billing.
  - **Admin-created members** are persisted with `accepted_at` set (`app/Filament/Admin/Resources/Members/Pages/CreateMember.php`), so `MemberObserver::created()` (`app/Observers/MemberObserver.php`) applies the same billing directly. `created()` returns early for pending (`accepted_at === null`) members.
- Invoice generation (`app/Domain/Invoices/InvoiceGeneratorImpl.php`) reads active `BillableItemInstance`s via `BillableItemsViewRepository::listBillableItemsForMember()` (`app/Infrastructure/Billing/BillableItemsViewDbRepository.php`), which already excludes an instance once an `InvoiceLine` for that `(member, billable_item)` exists.
- The cost center `8115` "Inschrijf geld" (`CostCenterNumber::RegistrationFee`) already exists in `database/seeders/CostCenterSeeder.php`, but there is **no `BillableItem` / `ExtraMembershipItem`** for the fee yet.

## Key finding: `BillPeriod::Once` is not wired through the recurring billing path

The natural way to express "appear on exactly the first invoice, never again" is `BillPeriod::Once`, whose `toBillPeriodInMonths()` returns `PHP_INT_MAX`. However, `BillPeriod::Once` is **currently only used by `MemberObject` billing, which bypasses `BillableItemInstance` entirely** (it writes an `InvoiceLine` directly in `app/Observers/MemberObjectObserver.php`). No real item has ever flowed a `Once` instance through `billableItemQuery` / `quantityFor`, and that path is broken for `Once` in two ways:

1. **`quantityFor` overflows.** `app/Models/BillableItemInstance::quantityFor()` computes `$anchor->addMonths($cycle)` with `$cycle = PHP_INT_MAX` once it passes the `$cycle <= 1` guard (unless `prorate === false` short-circuits first). `addMonths(PHP_INT_MAX)` exceeds the supported year range and throws.
2. **`Once` re-bills annually.** In `BillableItemsViewDbRepository::billableItemQuery()`, the `whereNotExists` "already billed?" lookback is `((month - bill_month + 12) % 12) % bill_cycle_in_months`. For `bill_cycle_in_months = PHP_INT_MAX` this reduces to `(month - bill_month + 12) % 12` — an annual offset. A `Once` item billed in, say, August 2026 would be excluded through December 2026 (window = Jan 1 2026) but would **re-appear in January 2027** (window = Jan 1 2027), i.e. it behaves like an annual item anchored to `bill_month`.

The plan therefore includes two small, targeted fixes that make `Once` behave correctly through the instance path (see Changes 6 and 7), plus a feature/scenario assertion proving the fee appears exactly once.

## Summary of planned changes

1. Add `ExtraMembershipItemCode::Inschrijfgeld = 'inschrijfgeld'`.
2. Seed an `ExtraMembershipItem` (`inschrijfgeld`) linked to a `BillableItem` (description "Inschrijfgeld", price 10, VAT 2.10, `BillPeriod::Once`, `prorate = false`, cost center 8115).
3. Add `ApplyRegistrationFeeBilling` applicator (interface + impl).
4. Wire it into `ApplyMemberAcceptedBilling` (public-registration path).
5. Wire it into `MemberObserver::created()` (admin-created path).
6. Make `quantityFor()` return `1.0` for `BillPeriod::Once` items.
7. Make the invoice-generation appearance query treat `Once` items as "excluded once any invoice line exists".
8. Tests and domain docs.

---

## Change: `ExtraMembershipItemCode` enum

File `app/Domain/Members/ExtraMembershipItemCode.php`.

Add the case (ordering is arbitrary; placing it after the volunteer cases keeps fees together):

```php
enum ExtraMembershipItemCode: string
{
    case VolunteerRestitution = 'vrijwilliger_restitutie';
    case VolunteerContribution = 'vrijwilligers_bijdrage';
    case Inschrijfgeld = 'inschrijfgeld';
    case SameHouseholdDiscountYoungster = 'zelfde_huishouden_korting_jeugd';
    case SameHouseholdDiscountAdult = 'zelfde_huishouden_korting_volwassen';
}
```

---

## Change: Seed the registration fee as an `ExtraMembershipItem`

File `database/seeders/MembershipSeeder.php`.

The fee's cost center is `8115` (RegistrationFee), which is distinct from the `Contributie` cost center already resolved at the top of this seeder. Resolve it and append the fee item. `BillPeriod::Once` gives the one-time semantic; `prorate => false` is correct for a flat fee and, before Change 6 lands, keeps `quantityFor` out of the overflow path.

```php
$registrationFeeCostCenter = CostCenter::query()
    ->where('number', CostCenterNumber::RegistrationFee)
    ->firstOrFail();

ExtraMembershipItem::factory()
    ->for(
        BillableItem::factory()
            ->state([
                'price' => 10,
                'vat' => 2.10,
                'bill_period' => BillPeriod::Once,
                'prorate' => false,
                'description' => 'Inschrijfgeld',
            ])
            ->for($registrationFeeCostCenter),
    )
    ->create([
        'code' => ExtraMembershipItemCode::Inschrijfgeld,
    ]);
```

`CostCenterNumber` and `BillPeriod` are already imported at the top of this file; `CostCenter` and `ExtraMembershipItemCode` are already imported.

---

## Change: `ApplyRegistrationFeeBilling` applicator

### Interface

File `app/Domain/Billing/BillingItemApplicators/ApplyRegistrationFeeBilling.php` (new):

```php
<?php

declare(strict_types=1);

namespace App\Domain\Billing\BillingItemApplicators;

use App\Domain\Members\MemberId;
use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface ApplyRegistrationFeeBilling
{
    public function apply(MemberId $memberId): void;
}
```

### Implementation

File `app/Domain/Billing/BillingItemApplicators/ApplyRegistrationFeeBillingImpl.php` (new):

```php
<?php

declare(strict_types=1);

namespace App\Domain\Billing\BillingItemApplicators;

use App\Domain\Billing\BillableItemInstanceRepository;
use App\Domain\Members\ExtraMembershipBillingItemRepository;
use App\Domain\Members\ExtraMembershipItemCode;
use App\Domain\Members\MemberId;
use Override;

final readonly class ApplyRegistrationFeeBillingImpl implements ApplyRegistrationFeeBilling
{
    public function __construct(
        private ExtraMembershipBillingItemRepository $extraMembershipBillingItemRepository,
        private BillableItemInstanceRepository $billableItemInstanceRepository,
    ) {}

    #[Override]
    public function apply(MemberId $memberId): void
    {
        $registrationFeeId = $this->extraMembershipBillingItemRepository
            ->getByCode(ExtraMembershipItemCode::Inschrijfgeld);

        $this->billableItemInstanceRepository->add($memberId, $registrationFeeId, null);
    }
}
```

`add(..., null)` passes a null `endDate` (indefinitely active), matching how `ApplyMembershipBillingImpl` and `ApplyMemberVolunteerBillingImpl` create recurring instances. Because `BillPeriod::Once` (with Changes 6 and 7) makes the fee bill exactly once, no explicit `endDate` is needed.

`Autowire` resolves the interface to this implementation automatically (same mechanism as the other applicators; `bootstrap/app.php` discovers `app/Domain`).

---

## Change: Wire into `ApplyMemberAcceptedBilling` (public-registration path)

File `app/Domain/Members/Listeners/ApplyMemberAcceptedBilling.php`.

Add the dependency and invoke it in `handle()`:

```php
use App\Domain\Billing\BillingItemApplicators\ApplyRegistrationFeeBilling;

final readonly class ApplyMemberAcceptedBilling
{
    public function __construct(
        private MemberRepository $memberRepository,
        private ApplyMembershipBilling $applyMembershipBilling,
        private ApplyMemberVolunteerBilling $applyMemberVolunteerBilling,
        private ApplySameHouseholdBilling $applySameHouseholdBilling,
        private ApplyRegistrationFeeBilling $applyRegistrationFeeBilling,
    ) {}

    public function handle(MemberAccepted $event): void
    {
        $member = $this->memberRepository->getById($event->memberId);

        $this->applyMembershipBilling->apply($event->memberId, $member->membershipId);
        $this->applyMemberVolunteerBilling->apply($event->memberId);
        $this->applySameHouseholdBilling->apply($event->memberId);
        $this->applyRegistrationFeeBilling->apply($event->memberId);
    }
}
```

---

## Change: Wire into `MemberObserver::created()` (admin-created path)

File `app/Observers/MemberObserver.php`.

Add the dependency and call it in the accepted branch of `created()`. The early return for pending members (`accepted_at === null`) already guarantees the fee is not applied until acceptance, and admin-created members (accepted at creation) reach this branch exactly once:

```php
use App\Domain\Billing\BillingItemApplicators\ApplyRegistrationFeeBilling;

final readonly class MemberObserver
{
    public function __construct(
        private ApplyMemberVolunteerBilling $applyMemberVolunteerBilling,
        private ApplyMembershipBilling $applyMembershipBilling,
        private ApplySameHouseholdBilling $applySameHouseholdBilling,
        private ApplyStoppedMembership $applyStoppedMembership,
        private ApplyRegistrationFeeBilling $applyRegistrationFeeBilling,
    ) {}

    public function created(Member $member): void
    {
        if ($member->accepted_at === null) {
            return; // pending members are billed on acceptance (ApplyMemberAcceptedBilling)
        }

        $this->applyMembershipBilling->apply(
            MemberId::create($member->id),
            MembershipId::create($member->membership_id),
        );

        $this->applyMemberVolunteerBilling->apply(MemberId::create($member->id));
        $this->applySameHouseholdBilling->apply(MemberId::create($member->id));
        $this->applyRegistrationFeeBilling->apply(MemberId::create($member->id));
    }

    // updated() unchanged
}
```

The two trigger points are mutually exclusive: a public-registration member is created pending (observer returns early) and later billed by `ApplyMemberAcceptedBilling`; an admin-created member is accepted at creation and billed by the observer. No member passes through both, so the fee is added exactly once.

---

## Change: `quantityFor` returns full quantity for `Once`

File `app/Models/BillableItemInstance.php`.

Add a `BillPeriod` import and short-circuit after the future-start guard, before the `$cycle` arithmetic (which would otherwise call `addMonths(PHP_INT_MAX)`):

```php
use App\Domain\Billing\BillPeriod;
```

```php
public function quantityFor(DateTimeInterface $when): float
{
    if ($this->start_date->startOfMonth()->gt($when)) {
        return 0.0;
    }

    if ($this->billableItem->bill_period === BillPeriod::Once) {
        return 1.0;
    }

    $cycle = (int) $this->bill_cycle_in_months;
    if ($cycle <= 1) {
        return 1.0;
    }

    if ($this->billableItem->prorate === false) {
        return 1.0;
    }

    // ... existing partial-quantity calculation unchanged ...
}
```

`billableItem` is already loaded by the production caller (`->with('billableItem')` in `BillableItemsViewDbRepository`), and lazy-loads in tests.

---

## Change: Invoice-generation appearance query excludes billed `Once` items forever

File `app/Infrastructure/Billing/BillableItemsViewDbRepository.php`, in `billableItemQuery()`.

The `whereNotExists` subquery references the outer `billable_items` table (already joined via `joinRelationship('billableItem')`). Prepend a `billable_items.bill_period = 'once'` term to the date predicate so that, for a `Once` item, *any* existing `InvoiceLine` (for the same member and `billable_item_id`) excludes the instance — regardless of how long ago it was billed.

SQLite branch — change:

```php
$query->whereRaw(
    "strftime('%Y-%m-01', invoices.date) >= date(?, '-' || (((? - billable_item_instances.bill_month + 12) % 12) % billable_item_instances.bill_cycle_in_months) || ' months')",
    [$date, $month],
);
```

to:

```php
$query->whereRaw(
    "billable_items.bill_period = 'once' OR strftime('%Y-%m-01', invoices.date) >= date(?, '-' || (((? - billable_item_instances.bill_month + 12) % 12) % billable_item_instances.bill_cycle_in_months) || ' months')",
    [$date, $month],
);
```

PostgreSQL branch — change:

```php
$query->whereRaw(
    "DATE_TRUNC('month', invoices.date) >= '{$date}'::date - MAKE_INTERVAL(0, ((({$month} - billable_item_instances.bill_month + 12) % 12) % billable_item_instances.bill_cycle_in_months))",
);
```

to:

```php
$query->whereRaw(
    "billable_items.bill_period = 'once' OR DATE_TRUNC('month', invoices.date) >= '{$date}'::date - MAKE_INTERVAL(0, ((({$month} - billable_item_instances.bill_month + 12) % 12) % billable_item_instances.bill_cycle_in_months))",
);
```

---

## Change: Tests

### New unit test — applicator

File `tests/Unit/Domain/Billing/BillingItemApplicators/ApplyRegistrationFeeBillingImplTest.php` (new), mirroring `ApplyMemberVolunteerBillingImplTest` but without member-state branching:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Billing\BillingItemApplicators;

use App\Domain\Billing\BillableItemId;
use App\Domain\Billing\BillableItemInstanceId;
use App\Domain\Billing\BillingItemApplicators\ApplyRegistrationFeeBillingImpl;
use App\Domain\Members\ExtraMembershipItemCode;
use App\Domain\Members\MemberId;
use Override;
use Tests\Unit\Domain\Invoices\BillableItemRepositoryExpectation;
use Tests\Unit\Domain\Members\ExtraMembershipBillingItemRepositoryExpectation;
use Tests\UnitTestCase;

final class ApplyRegistrationFeeBillingImplTest extends UnitTestCase
{
    private ExtraMembershipBillingItemRepositoryExpectation $extraMembershipBillingItemRepository;

    private BillableItemRepositoryExpectation $billableItemRepository;

    private ApplyRegistrationFeeBillingImpl $subject;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->extraMembershipBillingItemRepository = ExtraMembershipBillingItemRepositoryExpectation::create();
        $this->billableItemRepository = BillableItemRepositoryExpectation::create();

        $this->subject = new ApplyRegistrationFeeBillingImpl(
            $this->extraMembershipBillingItemRepository->mock,
            $this->billableItemRepository->mock,
        );
    }

    public function test_it_adds_the_registration_fee_instance(): void
    {
        $memberId = MemberId::create(1);
        $feeId = BillableItemId::create(30);

        $this->extraMembershipBillingItemRepository
            ->expectsGetByCode(ExtraMembershipItemCode::Inschrijfgeld, $feeId);
        $this->billableItemRepository
            ->expectsAdd($memberId, $feeId, null, BillableItemInstanceId::create(31));

        $this->subject->apply($memberId);
    }
}
```

### New expectation helper

File `tests/Unit/Domain/Billing/BillingItemApplicators/ApplyRegistrationFeeBillingExpectation.php` (new), mirroring `ApplyMemberVolunteerBillingExpectation`:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Billing\BillingItemApplicators;

use App\Domain\Billing\BillingItemApplicators\ApplyRegistrationFeeBilling;
use App\Domain\Members\MemberId;
use Mockery;
use Mockery\MockInterface;

use function PHPUnit\Framework\equalTo;

final readonly class ApplyRegistrationFeeBillingExpectation
{
    private function __construct(
        public MockInterface&ApplyRegistrationFeeBilling $mock,
    ) {}

    public static function create(): self
    {
        return new self(Mockery::mock(ApplyRegistrationFeeBilling::class));
    }

    public function expectsApply(MemberId $memberId): void
    {
        $this->mock
            ->expects('apply')
            ->with(equalTo($memberId))
            ->andReturnNull();
    }

    public function expectsApplyNever(): void
    {
        $this->mock
            ->expects('apply')
            ->never();
    }
}
```

### Update `ApplyMemberAcceptedBillingTest`

File `tests/Unit/Domain/Members/Listeners/ApplyMemberAcceptedBillingTest.php`.

Add the new expectation, inject it into the constructor, and assert it is applied:

```php
use Tests\Unit\Domain\Billing\BillingItemApplicators\ApplyRegistrationFeeBillingExpectation;

// setUp(): add
$this->registrationFeeBilling = ApplyRegistrationFeeBillingExpectation::create();

// constructor call gains the mock as the 5th argument

// test: add
$this->registrationFeeBilling->expectsApply($memberId);
```

### Update `MemberObserverTest`

File `tests/Feature/Observers/MemberObserverTest.php`.

Inject `ApplyRegistrationFeeBillingExpectation` and assert:

- `test_it_applies_billing_on_created_members`: add `$this->registrationFee->expectsApply($memberId);`.
- `test_it_does_not_apply_billing_on_created_pending_members`: add `$this->registrationFee->expectsApplyNever();`.

### Update `WindsurferLifetimeScenarioTest`

File `tests/Feature/Scenarios/WindsurferLifetimeScenarioTest.php`.

The first invoice now carries the fee; subsequent invoices must not. Update the Step 3 assertions (line count and add the fee line) and assert the fee is absent from later invoices:

```php
$firstInvoice = $this->invoiceFor($member, '2026-08-15');
static::assertSame(4, $firstInvoice->lines()->count());
$this->assertInvoiceLine($firstInvoice, 'Lidmaatschap Windsurfer (volwassenen)', 68.00, 0.42);
$this->assertInvoiceLine($firstInvoice, 'Vrijwilligersbijdrage', 20.00, 1.0);
$this->assertInvoiceLine($firstInvoice, 'Inschrijfgeld', 10.00, 1.0);
$this->assertInvoiceLine($firstInvoice, 'Activiteit: Reguliere les volwassenen', 37.00, 1.0);
```

Add a missing-assertion on a later invoice (Step 4, the second invoice, is the most direct proof it does not re-bill):

```php
$this->assertDatabaseMissing('invoice_lines', [
    'invoice_id' => $secondInvoice->id,
    'description' => 'Inschrijfgeld',
]);
```

This scenario test is the end-to-end proof of "first invoice only": the fee is billed on the 2026-08 invoice and never again (the January 2027 invoice, which re-bills the annual items, must not include it).

### New `quantityFor` case

File `tests/Feature/Infrastructure/Billing/BillableItemInstanceTest.php`.

Add a case proving a `Once` item returns full quantity without the old overflow:

```php
public function test_quantity_for_returns_full_quantity_for_once_items(): void
{
    $instance = BillableItemInstance::factory()
        ->for(Member::factory()->createQuietly())
        ->for(BillableItem::factory()->state([
            'bill_period' => BillPeriod::Once,
            'prorate' => true,
        ]))
        ->create([
            'bill_cycle_in_months' => BillPeriod::Once->toBillPeriodInMonths(),
            'bill_month' => 1,
            'start_date' => '2026-08-15',
        ]);

    $quantity = $instance->load('billableItem')->quantityFor(new DateTimeImmutable('2026-08-15'));

    static::assertSame(1.0, $quantity);
}
```

`BillPeriod` and `DateTimeImmutable` are already imported in this test file.

---

## Change: Domain documentation

File `/.agents/docs/GLOSSARY.md`, `ExtraMembershipItemCode` entry — extend the value list with `inschrijfgeld` (registration fee debit):

```markdown
**ExtraMembershipItemCode**:
A stable code identifying a billing adjustment item. Values are `vrijwilliger_restitutie` (volunteer restitution credit), `vrijwilligers_bijdrage` (volunteer contribution debit), `inschrijfgeld` (one-time registration fee debit), `zelfde_huishouden_korting_jeugd`, and `zelfde_huishouden_korting_volwassen` (same-household discounts for youngsters and adults). Owned by Members; consumed by Billing applicators to look up the matching BillableItem.
_Avoid_: adjustment code, discount code
```

File `/.agents/docs/billing/CONTEXT.md` — add a sentence to the `BillPeriod` term clarifying the `Once` semantics now enforced by the billing engine:

```markdown
**BillPeriod**:
The recurrence of a BillableItem: Once, Monthly, Quarterly, or Annually. Drives when instances appear in invoice batches and how quantities are calculated. A `Once` item bills at full quantity exactly once (the first invoice generated after it becomes active) and is then excluded from every subsequent invoice.
```

---

## Verification

- `./Taskfile artisan test --compact --filter=ApplyRegistrationFeeBilling`
- `./Taskfile artisan test --compact tests/Feature/Scenarios/WindsurferLifetimeScenarioTest.php`
- `./Taskfile artisan test --compact tests/Feature/Infrastructure/Billing/BillableItemInstanceTest.php`
- `./Taskfile artisan test --compact tests/Feature/Observers/MemberObserverTest.php`
- `./Taskfile artisan test --compact tests/Unit/Domain/Members/Listeners/ApplyMemberAcceptedBillingTest.php`
- `./Taskfile artisan migrate:fresh --seed` (dev) to materialize the fee `ExtraMembershipItem`/`BillableItem`.
- `./Taskfile stan` and `./Taskfile lint` to confirm domain purity and style.
