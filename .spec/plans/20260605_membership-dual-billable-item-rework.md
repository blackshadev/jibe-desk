# Plan: Membership Dual Billable Item Rework

## Goal

Each `Membership` currently holds a single `billable_item_id`, and the database contains separate rows for adult and kids variants of the same membership (e.g. "Lidmaatschap" and "Lidmaatschap Jeugd"). 

The goal is to consolidate these into one `Membership` row per type, where each membership carries both an `adult_billable_item_id` and a `kids_billable_item_id`. `ApplyMembershipBillingImpl` will use `Member::isYoungster()` (`age < 18`) to select the correct billable item at billing time.

---

## Key Files Reference

| Layer | File |
|---|---|
| Domain object | `app/Domain/Members/Membership.php` |
| Domain list | `app/Domain/Members/MembershipList.php` |
| Domain repository interface | `app/Domain/Members/MembershipRepository.php` |
| Billing applicator interface | `app/Domain/Invoices/Billing/BillingItemApplicators/ApplyMembershipBilling.php` |
| Billing applicator impl | `app/Domain/Invoices/Billing/BillingItemApplicators/ApplyMembershipBillingImpl.php` |
| Eloquent model | `app/Models/Membership.php` |
| DB repository | `app/Infrastructure/Members/MembershipDbRepository.php` |
| Filament form | `app/Filament/Admin/Resources/Memberships/Schemas/MembershipForm.php` |
| Filament table | `app/Filament/Admin/Resources/Memberships/Tables/MembershipsTable.php` |
| Factory | `database/factories/MembershipFactory.php` |

---

## Step 1 — Schema Migration

Create a new migration using `php artisan make:migration add_adult_kids_billable_items_to_memberships_table`.

The migration must:
1. Add `adult_billable_item_id` as a non-nullable FK to `billable_items`.
2. Add `kids_billable_item_id` as a non-nullable FK to `billable_items`.
3. Drop the FK constraint on `billable_item_id`, then drop the column itself.

```php
public function up(): void
{
    Schema::table('memberships', function (Blueprint $table) {
        $table->foreignId('adult_billable_item_id')->constrained('billable_items');
        $table->foreignId('kids_billable_item_id')->constrained('billable_items');
        $table->dropColumn('billable_item_id');
    });
}

public function down(): void
{
    Schema::table('memberships', function (Blueprint $table) {
        $table->foreignId('billable_item_id')->constrained('billable_items');
        $table->dropColumn('adult_billable_item_id');
        $table->dropColumn('kids_billable_item_id');
    });
}
```

> **Note:** The migration must run before any data migration. See Step 2.

---

## Step 2 — Data Migration

Not needed, we will seed from a fresh database using `task artisan migrate:fresh --seed`. We do need to update the factory and seeders for this.

---

## Step 3 — Domain Object: `Membership`

**File:** `app/Domain/Members/Membership.php`

Replace the single `$billableItemId` with two typed IDs:

```php
final readonly class Membership
{
    public function __construct(
        public MembershipId $id,
        public BillableItemId $adultBillableItemId,
        public BillableItemId $kidsBillableItemId,
    ) {
    }
}
```

---

## Step 4 — Domain List: `MembershipList`

**File:** `app/Domain/Members/MembershipList.php`

`asBillingIdList()` must collect **both** IDs from every membership so that `removeMany()` in the applicator clears whichever item was active (adult or kids):

```php
public function asBillingIdList(): BillableItemIdList
{
    $ids = [];
    foreach ($this->memberships as $membership) {
        $ids[] = $membership->adultBillableItemId;
        $ids[] = $membership->kidsBillableItemId;
    }

    return new BillableItemIdList($ids);
}
```

---

## Step 5 — Eloquent Model: `Membership`

**File:** `app/Models/Membership.php`

- Update `#[Fillable]` to include the two new FK columns.
- Replace `billableItem(): BelongsTo` with two separate relationships.

```php
#[Fillable(['name', 'adult_billable_item_id', 'kids_billable_item_id'])]
final class Membership extends Model
{
    use HasFactory;

    /** @return HasMany<Member, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }

    /** @return BelongsTo<BillableItem, $this> */
    public function adultBillableItem(): BelongsTo
    {
        return $this->belongsTo(BillableItem::class, 'adult_billable_item_id');
    }

    /** @return BelongsTo<BillableItem, $this> */
    public function kidsBillableItem(): BelongsTo
    {
        return $this->belongsTo(BillableItem::class, 'kids_billable_item_id');
    }
}
```

---

## Step 6 — Infrastructure: `MembershipDbRepository`

**File:** `app/Infrastructure/Members/MembershipDbRepository.php`

Update both `getById()` and `all()` to map the two new columns:

```php
new Membership(
    id: MembershipId::create($model->id),
    adultBillableItemId: BillableItemId::create($model->adult_billable_item_id),
    kidsBillableItemId: BillableItemId::create($model->kids_billable_item_id),
)
```

No interface change to `MembershipRepository` is required.

---

## Step 7 — Billing Applicator: `ApplyMembershipBillingImpl`

**File:** `app/Domain/Invoices/Billing/BillingItemApplicators/ApplyMembershipBillingImpl.php`

After fetching the member and the target membership, branch on `isYoungster()` to select the correct billable item:

```php
public function apply(MemberId $memberId, MembershipId $membershipId): void
{
    $member = $this->memberRepository->getById($memberId);

    $allBillingIds = $this->membershipRepository->all()->asBillingIdList();
    $this->billableItemRepository->removeMany($member->id, $allBillingIds);

    $newMembership = $this->membershipRepository->getById(MembershipId::create($membershipId->value));

    $billableItemId = $member->isYoungster()
        ? $newMembership->kidsBillableItemId
        : $newMembership->adultBillableItemId;

    $this->billableItemRepository->add($memberId, $billableItemId, null);
}
```

The `ApplyMembershipBilling` interface signature is unchanged.

---

## Step 8 — Filament Form: `MembershipForm`

**File:** `app/Filament/Admin/Resources/Memberships/Schemas/MembershipForm.php`

Replace the single `->relationship('billableItem')` section with two sections, one per relationship. Both sections use the same inner field structure and the same VAT mutation callback:

```php
Section::make(__('labels.billing_adults'))
    ->relationship('adultBillableItem')
    ->columnSpanFull()
    ->schema([
        TextInput::make('description')->label(__('labels.description'))->required(),
        TextInput::make('price')->label(__('labels.price'))->required(),
        Select::make('bill_period')
            ->label(__('labels.bill_period'))
            ->options(BillPeriodLabels::options())
            ->required(),
    ])
    ->mutateRelationshipDataBeforeCreateUsing(static fn (array $data): array => [
        ...$data,
        'vat' => $data['price'] * 0.21,
    ]),

Section::make(__('labels.billing_kids'))
    ->relationship('kidsBillableItem')
    ->columnSpanFull()
    ->schema([
        TextInput::make('description')->label(__('labels.description'))->required(),
        TextInput::make('price')->label(__('labels.price'))->required(),
        Select::make('bill_period')
            ->label(__('labels.bill_period'))
            ->options(BillPeriodLabels::options())
            ->required(),
    ])
    ->mutateRelationshipDataBeforeCreateUsing(static fn (array $data): array => [
        ...$data,
        'vat' => $data['price'] * 0.21,
    ]),
```

Add the two new translation keys `labels.billing_adults` and `labels.billing_kids` to the language files.

---

## Step 9 — Filament Table: `MembershipsTable`

**File:** `app/Filament/Admin/Resources/Memberships/Tables/MembershipsTable.php`

Replace the single `billableItem.price` column with two columns:

```php
TextColumn::make('adultBillableItem.price')
    ->label(__('labels.price_adults'))
    ->formatStateUsing(PriceFormatter::format(...)),

TextColumn::make('kidsBillableItem.price')
    ->label(__('labels.price_kids'))
    ->formatStateUsing(PriceFormatter::format(...)),
```

Add translation keys `labels.price_adults` and `labels.price_kids` to the language files.

---

## Step 10 — Factory: `MembershipFactory`

**File:** `database/factories/MembershipFactory.php`

Replace `billable_item_id` with two separate factory-created billable items:

```php
public function definition(): array
{
    return [
        'name' => $this->faker->word(),
        'adult_billable_item_id' => function (array $state) {
            return BillableItem::factory()->state([
                'description' => 'Lidmaatschap ' . $state['name'],
                'bill_period' => BillPeriod::Annually->value,
            ]);
        },
        'kids_billable_item_id' => function (array $state) {
            return BillableItem::factory()->state([
                'description' => 'Lidmaatschap Jeugd ' . $state['name'],
                'bill_period' => BillPeriod::Annually->value,
            ]);
        },
    ];
}
```

---

## Step 11 — Tests

### 11.1 Unit: `MembershipListTest`

**File:** `tests/Unit/Domain/Members/MembershipListTest.php`

Update `Membership` constructor calls to pass both IDs. The assertion must now cover both IDs per membership (four total for two memberships):

```php
public function test_it_converts_memberships_to_billing_ids(): void
{
    $subject = new MembershipList([
        new Membership(MembershipId::create(1), BillableItemId::create(10), BillableItemId::create(20)),
        new Membership(MembershipId::create(2), BillableItemId::create(30), BillableItemId::create(40)),
    ]);

    self::assertSame([10, 20, 30, 40], $subject->asBillingIdList()->toIntArray());
}
```

### 11.2 Unit: `ApplyMembershipBillingImplTest`

**File:** `tests/Unit/Domain/Invoices/Billing/BillingItemApplicators/ApplyMembershipBillingImplTest.php`

The existing single test becomes two separate tests — one for an adult member, one for a youngster.

Both tests share the same setup: two memberships each with distinct adult and kids IDs, and a `removeMany` expectation covering all four IDs.

**Test 1 — adult member applies adult billable item:**

```php
public function test_it_applies_adult_billable_item_for_adult_member(): void
{
    $memberId = MemberId::create(1);
    $membershipId = MembershipId::create(2);
    $adultBillableItemId1 = BillableItemId::create(10);
    $kidsBillableItemId1  = BillableItemId::create(11);
    $adultBillableItemId2 = BillableItemId::create(20);
    $kidsBillableItemId2  = BillableItemId::create(21);

    $member = new Member(
        id: $memberId,
        membershipId: $membershipId,
        isVolunteer: false,
        householdId: null,
        age: 25, // adult
    );

    $membership1 = new Membership(MembershipId::create(1), $adultBillableItemId1, $kidsBillableItemId1);
    $membership2 = new Membership($membershipId, $adultBillableItemId2, $kidsBillableItemId2);
    $allMemberships = new MembershipList([$membership1, $membership2]);

    $this->memberRepo->expectsGetById($memberId, $member);
    $this->membershipRepo->expectsAll($allMemberships);
    $this->billableRepo->expectsRemove(
        $memberId,
        new BillableItemIdList([$adultBillableItemId1, $kidsBillableItemId1, $adultBillableItemId2, $kidsBillableItemId2])
    );
    $this->membershipRepo->expectsGetById($membershipId, $membership2);
    $this->billableRepo->expectsAdd($memberId, $adultBillableItemId2, null, BillableItemInstanceId::create(12));

    $this->subject->apply($memberId, $membershipId);
}
```

**Test 2 — youngster member applies kids billable item:**

```php
public function test_it_applies_kids_billable_item_for_youngster_member(): void
{
    $memberId = MemberId::create(1);
    $membershipId = MembershipId::create(2);
    $adultBillableItemId1 = BillableItemId::create(10);
    $kidsBillableItemId1  = BillableItemId::create(11);
    $adultBillableItemId2 = BillableItemId::create(20);
    $kidsBillableItemId2  = BillableItemId::create(21);

    $member = new Member(
        id: $memberId,
        membershipId: $membershipId,
        isVolunteer: false,
        householdId: null,
        age: 14, // youngster
    );

    $membership1 = new Membership(MembershipId::create(1), $adultBillableItemId1, $kidsBillableItemId1);
    $membership2 = new Membership($membershipId, $adultBillableItemId2, $kidsBillableItemId2);
    $allMemberships = new MembershipList([$membership1, $membership2]);

    $this->memberRepo->expectsGetById($memberId, $member);
    $this->membershipRepo->expectsAll($allMemberships);
    $this->billableRepo->expectsRemove(
        $memberId,
        new BillableItemIdList([$adultBillableItemId1, $kidsBillableItemId1, $adultBillableItemId2, $kidsBillableItemId2])
    );
    $this->membershipRepo->expectsGetById($membershipId, $membership2);
    $this->billableRepo->expectsAdd($memberId, $kidsBillableItemId2, null, BillableItemInstanceId::create(12));

    $this->subject->apply($memberId, $membershipId);
}
```

### 11.3 Feature: `MembershipDbRepositoryTest`

**File:** `tests/Feature/Infrastructure/Members/MembershipDbRepositoryTest.php`

The factory now creates two billable items automatically. Update assertions accordingly.

- `test_all_returns_all_memberships_with_billable_item_ids`: assert that **both** `adult_billable_item_id` and `kids_billable_item_id` for each model are present in the result list.
- `test_get_by_id_returns_membership_domain_object`: assert `$domain->adultBillableItemId->value` and `$domain->kidsBillableItemId->value` match the model's respective columns.

```php
public function test_all_returns_all_memberships_with_billable_item_ids(): void
{
    $model1 = Membership::factory()->create();
    $model2 = Membership::factory()->create();

    $repo = new MembershipDbRepository();
    $ids = $repo->all()->asBillingIdList()->toIntArray();

    self::assertContains($model1->adult_billable_item_id, $ids);
    self::assertContains($model1->kids_billable_item_id, $ids);
    self::assertContains($model2->adult_billable_item_id, $ids);
    self::assertContains($model2->kids_billable_item_id, $ids);
}

public function test_get_by_id_returns_membership_domain_object(): void
{
    $model = Membership::factory()->create();

    $repo = new MembershipDbRepository();
    $domain = $repo->getById(MembershipId::create($model->id));

    self::assertSame($model->id, $domain->id->value);
    self::assertSame($model->adult_billable_item_id, $domain->adultBillableItemId->value);
    self::assertSame($model->kids_billable_item_id, $domain->kidsBillableItemId->value);
}
```

### 11.4 No Changes Required

The following test files need no structural changes. Any inline `new Membership(...)` constructions found inside them must have the constructor updated to pass both IDs, but the test logic itself is unaffected:

- `tests/Feature/Observers/MemberObserverTest.php` — uses mocked `ApplyMembershipBilling`; no direct `Membership` construction.
- `tests/Unit/Domain/Members/MembershipRepositoryExpectation.php` — operates on the domain object generically; no constructor calls.
- `tests/Unit/Domain/Invoices/BillableItemRepositoryExpectation.php` — unrelated to `Membership` structure.
- `tests/Feature/Infrastructure/Invoices/BillableItemDbInstanceRepositoryTest.php` — operates on abstract `BillableItemId` values.

---

## Side Note: Age-Change Billing Trigger (out of scope)

`MemberObserver` currently only re-runs `applyMembershipBilling` when `membership_id` changes. After this rework, a member crossing the age-18 boundary will not get their billing item updated until the membership is manually reassigned. A follow-up task should add an `age` / `date_of_birth` change handler to the observer that calls `applyMembershipBilling` with the member's current membership ID.

---

## Implementation Order

1. Schema migration (Step 1)
2. Data migration (Step 2) — requires decision on pairing logic
3. Domain object (Step 3)
4. Domain list (Step 4)
5. Eloquent model (Step 5)
6. Infrastructure repository (Step 6)
7. Billing applicator (Step 7)
8. Factory (Step 10) — required before tests run
9. Filament form (Step 8)
10. Filament table (Step 9)
11. Tests (Step 11)
12. Run `php artisan test --compact` and confirm all tests pass
