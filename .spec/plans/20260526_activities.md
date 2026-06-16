# Activities Implementation Plan

## Overview

Activities are entities with a `name`, `start_date`, `end_date`, and a linked `BillableItem`. Members can participate in activities. When a member is linked to an activity, a `BillableItemInstance` is created for that member, using the activity's `start_date`, `end_date`, and `bill_period` (derived from the linked `BillableItem`).

---

## 1. Migration

### `create_activities_table`

```php
Schema::create('activities', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->text('description')->nullable();
    $table->foreignId('billable_item_id')->constrained()->cascadeOnDelete();
    $table->date('start_date');
    $table->date('end_date')->nullable();
    $table->timestamps();
});
```

### `create_activity_member_table` (pivot)

```php
Schema::create('activity_member', function (Blueprint $table) {
    $table->id();
    $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
    $table->foreignId('member_id')->constrained()->cascadeOnDelete();
    $table->foreignId('billable_item_instance_id')->nullable()->constrained()->nullOnDelete();
    $table->timestamps();

    $table->unique(['activity_id', 'member_id']);
});
```

The `billable_item_instance_id` is stored so that when a member is removed from an activity, the linked `BillableItemInstance` can be stopped automatically.

---

## 2. Eloquent Model: `App\Models\Activity`

File: `app/Models/Activity.php`

- `HasFactory`
- `#[Fillable(['name', 'description', 'billable_item_id', 'start_date', 'end_date'])]`
- Relationships:
  - `billableItem(): BelongsTo<BillableItem, $this>`
  - `members(): BelongsToMany<Member, $this>` — through `activity_member`, with pivot: `billable_item_instance_id`
- Casts: `start_date` and `end_date` as `'date'`

---

## 3. Update `App\Models\Member`

Add the inverse relationship:

```php
/** @return BelongsToMany<Activity, $this> */
public function activities(): BelongsToMany
{
    return $this->belongsToMany(Activity::class)->withPivot('billable_item_instance_id')->withTimestamps();
}
```

---

## 4. Factory: `database/factories/ActivityFactory.php`

```php
final class ActivityFactory extends Factory
{
    protected $model = Activity::class;

    public function definition(): array
    {
        $name = $this->faker->words(3, true);
        $startDate = $this->faker->dateTimeBetween('-1 year', '+6 months');
        $endDate = $this->faker->optional()->dateTimeBetween($startDate, '+1 year');
        return [
            'name' => $name,
            'description' => $this->faker->sentence(),
            'billable_item_id' => BillableItem::factory()->state([ 
                'bill_period' => BillPeriod::Monthly->value,
                'description' => 'Activiteit ' + $name
            ]),
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];
    }
}
```

---

## 5. Domain Layer

### `App\Domain\Activities\ActivityId`

File: `app/Domain/Activities/ActivityId.php`

Follows the same pattern as `MemberId`, `MembershipId`:

```php
final readonly class ActivityId extends NumericId
{
    public static function create(int $value): self
    {
        return new self($value);
    }
}
```

### `App\Domain\Activities\Activity` (domain object)

File: `app/Domain/Activities/Activity.php`

```php
final readonly class Activity
{
    public function __construct(
        public ActivityId $id,
        public BillableItemId $billableItemId,
        public DateTimeImmutable $startDate,
        public ?DateTimeImmutable $endDate,
    ) {}
}
```

### `App\Domain\Activities\ActivityRepository`

File: `app/Domain/Activities/ActivityRepository.php`

```php
#[Autowire]
interface ActivityRepository
{
    public function getById(ActivityId $activityId): Activity;
}
```

### `App\Infrastructure\Activities\ActivityDbRepository`

File: `app/Infrastructure/Activities/ActivityDbRepository.php`

Implements `ActivityRepository`. Fetches `App\Models\Activity` and maps to the domain object.

---

## 6. ApplyActivityBilling

### `App\Domain\Invoices\Billing\ApplyActivityBilling`

File: `app/Domain/Invoices/Billing/ApplyActivityBilling.php`

Implements `ApplyBillableItem<ActivityId>`.

**Logic:**

1. Receive `MemberId $memberId` and `ActivityId $activityId`.
2. Fetch the `Activity` domain object via `ActivityRepository`.
3. Fetch the `BillableItem` model to get `bill_period`.
4. Create a `BillableItemInstance` for the member, using:
   - `start_date` = activity's `start_date`
   - `end_date` = activity's `end_date`
   - `bill_cycle_in_months` from `BillableItem->bill_period->toBillPeriodInMonths()`
5. Store the new `billable_item_instance_id` on the `activity_member` pivot row.

This action is triggered when a member is attached to an activity.

**Reversing (removing a member from an activity):**

When a member is detached from an activity, the linked `BillableItemInstance` should be stopped (set `end_date = now()`). This can be handled by:
- Introducing a `RemoveActivityBilling` service, OR
- Handling it directly in a Filament action on the relation manager.

Recommended: create a method `stopActivityBillingForMember(MemberId, ActivityId)` on `ApplyActivityBilling` or a companion class/action.

**Full class sketch:**

```php
/** @implements ApplyBillableItem<ActivityId> */
final readonly class ApplyActivityBilling implements ApplyBillableItem
{
    public function __construct(
        private ActivityRepository $activityRepository,
        private BillableItemInstanceRepository $billableItemInstanceRepository,
    ) {}

    public function __invoke(MemberId $memberId, ?NumericId $activityId): void
    {
        /** @var ActivityId $activityId */
        $activity = $this->activityRepository->getById(ActivityId::create($activityId->value));

        // Uses a new method on BillableItemInstanceRepository (see below)
        $this->billableItemInstanceRepository->addForActivity($memberId, $activity);
    }
}
```

### Extend `BillableItemInstanceRepository`

Add a new method to the interface and implementation:

```php
// Interface
public function addForActivity(MemberId $memberId, Activity $activity): BillableItemId;
```

The implementation creates a `BillableItemInstance` with:
- `billable_item_id` from `$activity->billableItemId`
- `start_date` from `$activity->startDate`
- `end_date` from `$activity->endDate`
- `bill_cycle_in_months` from the associated `BillableItem->bill_period->toBillPeriodInMonths()`

Returns the created `BillableItemInstance` id so it can be stored on the pivot.

> **Note**: The pivot `billable_item_instance_id` update happens in the Filament relation manager (see below), not in `ApplyActivityBilling`, to keep the domain layer clean. Alternatively, a dedicated service/action can handle the full flow including the pivot update.

---

## 7. Filament Resource: `ActivityResource`

### Structure

Follow the same folder pattern as `MembershipResource`:

```
app/Filament/Admin/Resources/Activities/
    ActivityResource.php
    Pages/
        ListActivities.php
        CreateActivity.php
        EditActivity.php
    Schemas/
        ActivityForm.php
    Tables/
        ActivitiesTable.php
    RelationManagers/
        ActivityMembersRelationManager.php
```

### `ActivityResource.php`

- Model: `App\Models\Activity`
- Navigation group: `NavigationGroup::MemberAdministration`
- Navigation icon: `Heroicon::CalendarDays` (or similar)
- `$recordTitleAttribute = 'name'`
- Relations: `[ActivityMembersRelationManager::class]`
- Pages: List, Create, Edit

### `ActivityForm.php`

Fields:
- `TextInput::make('name')` — required
- `Textarea::make('description')` — optional
- `DatePicker::make('start_date')` — required
- `DatePicker::make('end_date')` — optional
- Billing section using `Section::make()->relationship('billableItem')`:
  - `TextInput::make('description')` — required
  - `TextInput::make('price')` — required
  - `Select::make('bill_period')` with `BillPeriod` options — required

Pattern matches `ExtraMembershipItemForm`.

### `ActivitiesTable.php`

Columns:
- `name`
- `start_date` (date)
- `end_date` (date)
- `billableItem.description`
- `billableItem.price`
- `billableItem.bill_period`

### `ActivityMembersRelationManager.php`

- Relationship: `members`
- Shows members attached to an activity
- **Attach action**: when a member is attached, call `ApplyActivityBilling` to create a `BillableItemInstance`. Store the resulting instance id on the pivot.
- **Detach action**: when a member is detached, stop the associated `BillableItemInstance` (set `end_date = now()`).

Columns to show: member name, start_date of BillableItemInstance, end_date.

---

## 8. Add Activities Relation to Member (Filament)

In `MemberResource`, optionally add a read-only relation manager showing which activities a member belongs to. This is lower priority but consistent with the data model.

---

## 9. Labels / Translations

Add to `lang/*/labels.php` (or equivalent):

```php
'activity' => 'Activity',
'activities' => 'Activities',
```

---

## 10. Tests

All domain and infrastructure code must be covered. No Filament tests. Follow the project conventions:
- Unit tests extend `Tests\UnitTestCase`, live under `tests/Unit/Domain/`.
- Feature/infrastructure tests extend `Tests\FeatureTestCase`, live under `tests/Feature/Infrastructure/`.
- Expectation classes live alongside unit tests at `tests/Unit/Domain/<Context>/<Thing>Expectation.php`.
- Use `self::assertSame(...)` (static form) for assertions.

---

### 10.1 Unit test: `ActivityId`

**File:** `tests/Unit/Domain/Activities/ActivityIdTest.php`

Extend `Tests\Unit\Domain\NumericIdTestCase` (same pattern as `MemberIdTest`, `MembershipIdTest`):

```php
final class ActivityIdTest extends NumericIdTestCase
{
    protected function getSubject(): string
    {
        return ActivityId::class;
    }
}
```

---

### 10.2 Expectation class: `ActivityRepositoryExpectation`

**File:** `tests/Unit/Domain/Activities/ActivityRepositoryExpectation.php`

Used by `ApplyActivityBillingTest` to mock `ActivityRepository` without brittle inline matchers.

```php
final readonly class ActivityRepositoryExpectation
{
    private function __construct(public MockInterface&ActivityRepository $mock) {}

    public static function create(): self
    {
        return new self(Mockery::mock(ActivityRepository::class));
    }

    public function expectsGetById(ActivityId $activityId, Activity $activity): void
    {
        $this->mock
            ->expects('getById')
            ->with(equalTo($activityId))
            ->andReturn($activity);
    }
}
```

---

### 10.3 Extend `BillableItemRepositoryExpectation`

**File:** `tests/Unit/Domain/Invoices/BillableItemRepositoryExpectation.php` *(update)*

Add `expectsAddForActivity` to the existing expectation class so `ApplyActivityBillingTest` can express the expected interaction:

```php
public function expectsAddForActivity(MemberId $memberId, Activity $activity): void
{
    $this->mock
        ->expects('addForActivity')
        ->with(equalTo($memberId), equalTo($activity));
}
```

---

### 10.4 Unit test: `ApplyActivityBillingTest`

**File:** `tests/Unit/Domain/Invoices/ApplyActivityBillingTest.php`

Mirrors the structure of `ApplyMembershipBillingTest` and `ApplyMemberVolunteerBillingTest`. Uses `ActivityRepositoryExpectation` and `BillableItemRepositoryExpectation`. No real DB.

**Cases to cover:**

1. **`test_it_fetches_activity_and_creates_billable_item_instance`**
   - Build an `Activity` domain object with a known `ActivityId`, `BillableItemId`, `startDate`, and `endDate`.
   - Configure `ActivityRepositoryExpectation::expectsGetById` to return it.
   - Configure `BillableItemRepositoryExpectation::expectsAddForActivity` to expect the call with the same `MemberId` and `Activity`.
   - Invoke `ApplyActivityBilling` and assert the mocks are satisfied (Mockery verifies this automatically in `tearDown`).

2. **`test_it_passes_activity_with_null_end_date`**
   - Same as above but the `Activity` domain object has `endDate = null`, verifying the `null` flows through correctly to `addForActivity`.

---

### 10.5 Feature test: `ActivityDbRepositoryTest`

**File:** `tests/Feature/Infrastructure/Activities/ActivityDbRepositoryTest.php`

Tests that `ActivityDbRepository` correctly reads from the DB and maps to the domain object. Uses real DB + factories.

**Cases to cover:**

1. **`test_get_by_id_returns_activity_domain_object`**
   - Create an `Activity` model via `Activity::factory()->create()`.
   - Call `$repo->getById(ActivityId::create($model->id))`.
   - Assert that the returned domain object has matching `id->value`, `billableItemId->value`, `startDate`, and `endDate`.

2. **`test_get_by_id_throws_when_not_found`**
   - Expect `ModelNotFoundException`.
   - Call `$repo->getById(ActivityId::create(999999))`.

---

### 10.6 Feature test: `BillableItemDbInstanceRepositoryTest` *(extend existing)*

**File:** `tests/Feature/Infrastructure/Invoices/BillableItemDbInstanceRepositoryTest.php` *(update)*

Add new test cases for the new `addForActivity` method. Continue the existing class and `CarbonImmutable::setTestNow` pattern.

**Cases to add:**

1. **`test_add_for_activity_creates_instance_with_activity_dates`**
   - Create a `Member` and an `Activity` (with a `BillableItem` set to e.g. `bill_period = 'monthly'`).
   - Build an `Activity` domain object with specific `startDate` and `endDate` matching the DB record.
   - Call `$repo->addForActivity(MemberId::create($member->id), $activity)`.
   - Assert `assertDatabaseHas('billable_item_instances', [...])` with the correct `start_date`, `end_date`, `bill_cycle_in_months = 1`, `member_id`, and `billable_item_id`.

2. **`test_add_for_activity_creates_instance_with_null_end_date`**
   - Same setup but the `Activity` domain object has `endDate = null`.
   - Assert the resulting row has `end_date = null`.

3. **`test_add_for_activity_returns_billable_item_instance_id`**
   - Call `addForActivity` and capture the returned value (a `BillableItemInstanceId` or the raw id per the implementation choice).
   - Assert it matches the persisted record's primary key, so callers (e.g. pivot update) can use it.

---

## File Summary

| File | Action |
|------|--------|
| `database/migrations/..._create_activities_table.php` | New |
| `database/migrations/..._create_activity_member_table.php` | New |
| `app/Models/Activity.php` | New |
| `app/Models/Member.php` | Add `activities()` relationship |
| `database/factories/ActivityFactory.php` | New |
| `app/Domain/Activities/ActivityId.php` | New |
| `app/Domain/Activities/Activity.php` | New |
| `app/Domain/Activities/ActivityRepository.php` | New |
| `app/Infrastructure/Activities/ActivityDbRepository.php` | New |
| `app/Domain/Invoices/Billing/ApplyActivityBilling.php` | New |
| `app/Domain/Invoices/Billing/BillableItemInstanceRepository.php` | Add `addForActivity()` method |
| `app/Infrastructure/Invoices/Billing/BillableItemDbInstanceRepository.php` | Implement `addForActivity()` |
| `app/Filament/Admin/Resources/Activities/ActivityResource.php` | New |
| `app/Filament/Admin/Resources/Activities/Pages/` (3 files) | New |
| `app/Filament/Admin/Resources/Activities/Schemas/ActivityForm.php` | New |
| `app/Filament/Admin/Resources/Activities/Tables/ActivitiesTable.php` | New |
| `app/Filament/Admin/Resources/Activities/RelationManagers/ActivityMembersRelationManager.php` | New |
| `tests/Unit/Domain/Activities/ActivityIdTest.php` | New |
| `tests/Unit/Domain/Activities/ActivityRepositoryExpectation.php` | New |
| `tests/Unit/Domain/Invoices/BillableItemRepositoryExpectation.php` | Add `expectsAddForActivity` |
| `tests/Unit/Domain/Invoices/ApplyActivityBillingTest.php` | New |
| `tests/Feature/Infrastructure/Activities/ActivityDbRepositoryTest.php` | New |
| `tests/Feature/Infrastructure/Invoices/BillableItemDbInstanceRepositoryTest.php` | Add `addForActivity` cases |

