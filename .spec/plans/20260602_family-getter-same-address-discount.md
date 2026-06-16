# Plan: Household — Zelfde Adres Korting

## Overview

Group members into **households** via a join table. Any member belonging to a household automatically receives the `zelfde_adres_korting` discount on their billing. The correct discount tier (`zelfde_adres_korting_jeugd` vs `zelfde_adres_korting_volwassen`) is chosen based on the member's age (< 18 = jeugd, ≥ 18 = volwassen).

A member can belong to **at most one** household. The billing discount is applied/removed whenever household membership or birthdate changes, following the same `BillingItemApplicator` pattern as `ApplyMembershipBilling` and `ApplyMemberVolunteerBilling`.

---

## Existing Patterns to Follow

- **Applicator interface** with `#[Autowire]` in `app/Domain/Invoices/Billing/BillingItemApplicators/`
- **Applicator impl** (final readonly class) in the same directory
- **`MemberObserver`** (`app/Observers/MemberObserver.php`) injects applicators, calls them on model events
- **`ExtraMembershipBillingItemRepository`** resolves `BillableItemId` by `ExtraMembershipItemCode`
- **`BillableItemInstanceRepository`** methods: `removeMany()`, `add()`
- **`Member` domain entity** is a `final readonly class`
- **`MemberDbRepository`** maps Eloquent model → domain entity
- Discount codes already exist: `ExtraMembershipItemCode::SameHouseholdDiscountYoungster` / `SameHouseholdDiscountAdult`
- Age threshold: **18 years** (youngster = < 18, adult = ≥ 18)
- `Member` model already has a computed `age` Eloquent attribute (returns `int`)

---

## Data Model Design

```
households
  id (PK)
  timestamps

household_member  (pivot / join table)
  household_id  FK → households.id  (cascade delete)
  member_id     FK → members.id     (cascade delete)
  UNIQUE(member_id)   ← a member can be in at most one household
  timestamps
```

A `households` row is a bare identity record — it has no columns beyond `id`. All semantic meaning comes from which members are attached. A member not present in `household_member` belongs to no household and receives no discount.

---

## Implementation Steps

### 1. Create `households` and `household_member` Migrations

**Two migrations** (keep them separate for clarity):

```bash
php artisan make:migration create_households_table
php artisan make:migration create_household_member_table
```

**`create_households_table`:**
```php
Schema::create('households', function (Blueprint $table) {
    $table->id();
    $table->timestamps();
});
```

**`create_household_member_table`:**
```php
Schema::create('household_member', function (Blueprint $table) {
    $table->foreignId('household_id')->constrained()->cascadeOnDelete();
    $table->foreignId('member_id')->constrained()->cascadeOnDelete();
    $table->timestamps();

    $table->primary(['household_id', 'member_id']);
    $table->unique('member_id');
});
```

---

### 2. Create `Household` Eloquent Model

```bash
php artisan make:model Household
```

**File:** `app/Models/Household.php`

```php
final class Household extends Model
{
    use HasFactory;

    /** @return BelongsToMany<Member, $this, HouseholdMember> */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Member::class)
            ->using(HouseholdMember::class)
            ->withTimestamps();
    }
}
```

---

### 3. Create `HouseholdMember` Pivot Model

The pivot model exists so it can be **observed** — triggering billing recalculation when members are added/removed from a household.

```bash
php artisan make:model HouseholdMember --pivot
```

**File:** `app/Models/Pivots/HouseholdMember.php`

```php
final class HouseholdMember extends Pivot
{
    // Observed by HouseholdMemberObserver
}
```

Add `#[ObservedBy([HouseholdMemberObserver::class])]` attribute once the observer is created.

---

### 4. Update `Member` Eloquent Model

**File:** `app/Models/Member.php`

Add the `household` BelongsToMany relationship. Rename any previous `familyMembers` references to `householdMembers` (none exist yet):

```php
/** @return BelongsToMany<Household, $this, HouseholdMember> */
public function household(): BelongsToMany
{
    return $this->belongsToMany(Household::class)
        ->using(HouseholdMember::class)
        ->withTimestamps();
}
```

> A member logically belongs to one household, but because the join table enforces `UNIQUE(member_id)`, `$member->household()->first()` is the idiomatic way to fetch it.

---

### 5. Add Domain Value Object `HouseholdId`

**File:** `app/Domain/Members/HouseholdId.php`

Follow the exact same pattern as `MemberId`:

```php
final readonly class HouseholdId extends NumericId {}
```

---

### 6. Update Domain `Member` Entity

**File:** `app/Domain/Members/Member.php`

Add `householdId` (nullable) and `age`:

```php
final readonly class Member
{
    public function __construct(
        public MemberId $id,
        public MembershipId $membershipId,
        public bool $isVolunteer,
        public ?HouseholdId $householdId,
        public int $age,
    ) {}

    public function isInHousehold(): bool
    {
        return $this->householdId !== null;
    }

    public function isYoungster(): bool
    {
        return $this->age < 18;
    }
}
```

---

### 7. Update `MemberDbRepository`

**File:** `app/Infrastructure/Members/MemberDbRepository.php`

Eager-load the `household` pivot to resolve `householdId`:

```php
public function getById(MemberId $memberId): MemberEntity
{
    $model = Member::with('household')->findOrFail($memberId->value);

    $household = $model->household()->first();

    return new MemberEntity(
        id: MemberId::create($model->id),
        membershipId: MembershipId::create($model->membership_id),
        isVolunteer: $model->is_volunteer,
        householdId: $household ? HouseholdId::create($household->id) : null,
        age: $model->age,
    );
}
```

---

### 8. Create `ApplyHousehold` Interface

**File:** `app/Domain/Invoices/Billing/BillingItemApplicators/ApplyHouseholdBilling.php`

```php
<?php

declare(strict_types=1);

namespace App\Domain\Invoices\Billing\BillingItemApplicators;

use App\Domain\Members\MemberId;
use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface ApplyHouseholdBilling
{
    public function apply(MemberId $memberId): void;
}
```

---

### 9. Create `ApplyHouseholdBillingImpl`

**File:** `app/Domain/Invoices/Billing/BillingItemApplicators/ApplyHouseholdBillingImpl.php`

Logic:
1. Resolve both discount `BillableItemId`s
2. Always `removeMany` both from the member
3. If the member `isInHousehold()`, add the age-appropriate discount

```php
<?php

declare(strict_types=1);

namespace App\Domain\Invoices\Billing\BillingItemApplicators;

use App\Domain\Invoices\Billing\BillableItemIdList;
use App\Domain\Invoices\Billing\BillableItemInstanceRepository;
use App\Domain\Members\ExtraMembershipBillingItemRepository;
use App\Domain\Members\ExtraMembershipItemCode;
use App\Domain\Members\MemberId;
use App\Domain\Members\MemberRepository;

final readonly class ApplyHouseholdBillingImpl implements ApplyHouseholdBilling
{
    public function __construct(
        private ExtraMembershipBillingItemRepository $extraMembershipBillingItemRepository,
        private BillableItemInstanceRepository $billableItemInstanceRepository,
        private MemberRepository $memberRepository,
    ) {}

    public function apply(MemberId $memberId): void
    {
        $youngsterId = $this->extraMembershipBillingItemRepository->getByCode(ExtraMembershipItemCode::SameHouseholdDiscountYoungster);
        $adultId     = $this->extraMembershipBillingItemRepository->getByCode(ExtraMembershipItemCode::SameHouseholdDiscountAdult);

        $this->billableItemInstanceRepository->removeMany($memberId, new BillableItemIdList([$youngsterId, $adultId]));

        $member = $this->memberRepository->getById($memberId);

        if (!$member->isInHousehold()) {
            return;
        }

        $discountId = $member->isYoungster() ? $youngsterId : $adultId;
        $this->billableItemInstanceRepository->add($memberId, $discountId, null);
    }
}
```

---

### 10. Create `HouseholdMemberObserver`

**File:** `app/Observers/HouseholdMemberObserver.php`

When a `HouseholdMember` pivot row is **created** or **deleted**, re-apply the same-address billing for the affected member:

```php
final readonly class HouseholdMemberObserver
{
    public function __construct(
        private ApplySameHouseholdBilling $applySameHouseholdBilling,
    ) {}

    public function created(HouseholdMember $householdMember): void
    {
        $this->applySameHouseholdBilling->apply(MemberId::create($householdMember->member_id));
    }

    public function deleted(HouseholdMember $householdMember): void
    {
        $this->applySameHouseholdBilling->apply(MemberId::create($householdMember->member_id));
    }
}
```

Add `#[ObservedBy([HouseholdMemberObserver::class])]` to `HouseholdMember` pivot model.

---

### 12. Create `Household` Filament Resource

Create a dedicated Filament resource to manage households and their members.

```bash
php artisan make:filament-resource Household --generate
```

**File:** `app/Filament/Admin/Resources/Households/HouseholdResource.php`

- Place in the `member_administration` navigation group (matching `MemberResource`)
- The list table should show `id` and a count of members
- The edit page should have a `MembersRelationManager` or an inline `BelongsToManyCheckboxList` / repeater to add/remove members

**Relation manager for members on the Household edit page:**

```bash
php artisan make:filament-relation-manager HouseholdResource members name
```

File: `app/Filament/Admin/Resources/Households/RelationManagers/HouseholdMembersRelationManager.php`

- Table columns: member name, membership, age
- Actions: `AttachAction` (to add an existing member), `DetachAction` (to remove)
- **Do not** use `CreateAction` — members are created via `MemberResource`

---

### 13. Add `HouseholdRelationManager` to Member Edit Page

Optionally show the current household and its members from the member's perspective. Add a read-only relation manager tab on `EditMember`:

```bash
php artisan make:filament-relation-manager MemberResource household id
```

File: `app/Filament/Admin/Resources/Members/RelationManagers/HouseholdRelationManager.php`

- Shows the household the member belongs to
- Only displays; managing is done through `HouseholdResource`

---

### 14. Add Translation Labels

**File:** `lang/nl/labels.php`

```php
'household' => 'Huishouden',
'households' => 'Huishoudens',
'household_members' => 'Huishoudleden',
```

---

### 15. Create `HouseholdFactory` and Update `MemberFactory`

```bash
php artisan make:factory HouseholdFactory
```

**`HouseholdFactory`** — trivial (just creates the bare record).

**`MemberFactory`** — add an `inHousehold` state:

```php
public function inHousehold(Household $household): self
{
    return $this->afterCreating(function (Member $member) use ($household) {
        $household->members()->syncWithoutDetaching([$member->id]);
    });
}
```

---

## Tests to Write

### Expectation class — `ApplySameHouseholdBillingExpectation`

**File:** `tests/Unit/Domain/Invoices/Billing/BillingItemApplicators/ApplySameHouseholdBillingExpectation.php`

Follow the exact pattern of `ApplyMemberVolunteerBillingExpectation`. Expose `expectsApply(MemberId)`, `expectsApplyNever()`, and `allowsApply()`:

```php
final readonly class ApplySameHouseholdBillingExpectation
{
    private function __construct(public MockInterface&ApplySameHouseholdBilling $mock) {}

    public static function create(): self
    {
        return new self(Mockery::mock(ApplySameHouseholdBilling::class));
    }

    public function expectsApply(MemberId $memberId): void
    {
        $this->mock->expects('apply')->with(equalTo($memberId))->andReturnNull();
    }

    public function expectsApplyNever(): void
    {
        $this->mock->expects('apply')->never();
    }

    public function allowsApply(): void
    {
        $this->mock->allows('apply')->andReturnNull();
    }
}
```

---

### Unit test — `ApplySameHouseholdBillingImplTest`

**File:** `tests/Unit/Domain/Invoices/BillingItemApplicators/ApplySameHouseholdBillingImplTest.php`

Follow the pattern of `ApplyMemberVolunteerBillingImplTest`. Use `MemberRepositoryExpectation`, `ExtraMembershipBillingItemRepositoryExpectation`, and `BillableItemRepositoryExpectation`.

Note: `Member` domain entity constructor will need `householdId` and `age` args (see step 6 of implementation). Construct the `Member` entity directly in tests — no DB needed.

Cases:

1. **Member in a household, age < 18** → `removeMany` both discounts, then `add` the youngster discount
2. **Member in a household, age ≥ 18** → `removeMany` both discounts, then `add` the adult discount
3. **Member not in a household** → `removeMany` both discounts, no `add` call

```php
// Example for case 1
$memberId   = MemberId::create(1);
$youngsterId = BillableItemId::create(10);
$adultId     = BillableItemId::create(20);

$this->memberRepository->expectsGetById(
    $memberId,
    new Member($memberId, MembershipId::create(2), false, HouseholdId::create(5), age: 15)
);
$this->extraRepo->expectsGetByCode(ExtraMembershipItemCode::SameHouseholdDiscountYoungster, $youngsterId);
$this->extraRepo->expectsGetByCode(ExtraMembershipItemCode::SameHouseholdDiscountAdult, $adultId);
$this->billableRepo->expectsRemove($memberId, new BillableItemIdList([$youngsterId, $adultId]));
$this->billableRepo->expectsAdd($memberId, $youngsterId, null, BillableItemInstanceId::create(99));

$this->subject->apply($memberId);
```

---

### Feature test — `MemberObserverTest` (update existing)

**File:** `tests/Feature/Observers/MemberObserverTest.php`

Inject `ApplySameHouseholdBillingExpectation` into the existing test alongside the other two expectations. Add/update the following cases:

1. **`test_it_applies_billing_on_created_members`** — extend to also assert `applySameHousehold->expectsApply($memberId)`
2. **`test_it_applies_same_household_billing_on_birthdate_change`** — save member with changed `birthdate`, assert only `applySameHousehold->expectsApply()` fires
3. **`test_it_does_not_apply_same_household_billing_on_irrelevant_changes`** — extend the existing irrelevant-changes test to also assert `applySameHousehold->expectsApplyNever()`
4. **`test_it_does_not_apply_same_household_billing_on_volunteer_change`** — assert `applySameHousehold->expectsApplyNever()` when only `is_volunteer` changes
5. **`test_it_does_not_apply_same_household_billing_on_membership_change`** — assert `applySameHousehold->expectsApplyNever()` when only `membership_id` changes

---

### Feature test — `HouseholdMemberObserverTest`

**File:** `tests/Feature/Observers/HouseholdMemberObserverTest.php`

Follow the pattern of `ActivityMemberObserverTest`. Construct the observer directly with a mocked `ApplySameHouseholdBillingExpectation`.

Cases:

1. **`test_it_applies_billing_when_member_is_added_to_household`** — create a `HouseholdMember` pivot instance with `member_id`, call `$subject->created($pivot)`, assert `expectsApply(MemberId::create($pivot->member_id))`
2. **`test_it_applies_billing_when_member_is_removed_from_household`** — same setup, call `$subject->deleted($pivot)`, assert `expectsApply(MemberId::create($pivot->member_id))`

```php
// Example
$memberId = MemberId::create(7);

$pivot = new HouseholdMember([
    'household_id' => 3,
    'member_id'    => $memberId->value,
]);

$this->applySameHousehold->expectsApply($memberId);

$this->subject->created($pivot);
```

---

## Files Summary

| Action | File |
|--------|------|
| Create migration | `database/migrations/..._create_households_table.php` |
| Create migration | `database/migrations/..._create_household_member_table.php` |
| Create | `app/Models/Household.php` |
| Create | `app/Models/Pivots/HouseholdMember.php` |
| Update | `app/Models/Member.php` |
| Create | `app/Domain/Members/HouseholdId.php` |
| Update | `app/Domain/Members/Member.php` |
| Update | `app/Infrastructure/Members/MemberDbRepository.php` |
| Create | `app/Domain/Invoices/Billing/BillingItemApplicators/ApplySameHouseholdBilling.php` |
| Create | `app/Domain/Invoices/Billing/BillingItemApplicators/ApplySameHouseholdBillingImpl.php` |
| Create | `app/Observers/HouseholdMemberObserver.php` |
| Update | `app/Observers/MemberObserver.php` |
| Create | `app/Filament/Admin/Resources/Households/HouseholdResource.php` |
| Create | `app/Filament/Admin/Resources/Households/RelationManagers/HouseholdMembersRelationManager.php` |
| Create | `app/Filament/Admin/Resources/Members/RelationManagers/HouseholdRelationManager.php` |
| Update | `lang/nl/labels.php` |
| Create | `database/factories/HouseholdFactory.php` |
| Update | `database/factories/MemberFactory.php` |
| Create | `tests/Unit/Domain/Invoices/Billing/BillingItemApplicators/ApplySameHouseholdBillingExpectation.php` |
| Create | `tests/Unit/Domain/Invoices/BillingItemApplicators/ApplySameHouseholdBillingImplTest.php` |
| Update | `tests/Feature/Observers/MemberObserverTest.php` |
| Create | `tests/Feature/Observers/HouseholdMemberObserverTest.php` |
