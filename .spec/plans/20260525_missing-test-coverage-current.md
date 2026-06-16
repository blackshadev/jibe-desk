# Missing Test Coverage Plan (Current State)

## Overview

This plan reflects the **current** remaining test gaps in `app/Domain` and `app/Infrastructure` after the recently added unit, feature, and observer tests.

Based on the current codebase, **most Domain logic is already covered**. The meaningful remaining gaps are concentrated in a few **Infrastructure repositories** and a couple of **missing repository method scenarios** in existing feature tests.

## Testing conventions to follow

- **Unit tests** for pure Domain logic only
  - Path: `tests/Unit/Domain/<Context>/<Thing>Test.php`
  - Base class: `Tests\UnitTestCase`
- **Feature tests** for Infrastructure repositories and DB-backed behavior
  - Path: `tests/Feature/Infrastructure/<Context>/<Thing>Test.php`
  - Base class: `Tests\FeatureTestCase`
- **Expectation classes** only when mocking collaborators in Domain unit tests
  - Path: `tests/Unit/Domain/<Context>/<Thing>Expectation.php`
- Prefer `self::assert*` where possible.
- Use real DB fixtures and factories for repository coverage.

---

## 1. Remaining files that should receive additional test coverage

## 1.1 `app/Infrastructure/Invoices/Billing/BillableItemsViewDbRepository.php`

### Recommendation
- **Test type:** Feature test
- **File to create:** `tests/Feature/Infrastructure/Invoices/BillableItemsViewDbRepositoryTest.php`

### Why feature test
This class is entirely query-driven and depends on:
- joins
- `whereNotExists`
- invoice history filtering
- bill cycle window logic
- active/inactive billable item instance filtering
- Eloquent relation loading and domain mapping

This is integration behavior and should be tested against the real database.

### Important note
The repository now includes a sqlite-safe branch for the test environment, so it can be covered with feature tests.

### Source details
- `listBillableMembers(DateTimeInterface $when)` at `app/Infrastructure/Invoices/Billing/BillableItemsViewDbRepository.php:22`
- `listBillableItemsForMember(DateTimeInterface $when, MemberId $memberId)` at `app/Infrastructure/Invoices/Billing/BillableItemsViewDbRepository.php:33`
- shared query builder at `app/Infrastructure/Invoices/Billing/BillableItemsViewDbRepository.php:52`

### Behaviors to test

#### Test 1 — `listBillableMembers` returns distinct members with active billable items
- Create 2 members with active `billable_item_instances`
- Create multiple instances for one member to ensure `distinct('member_id')` behavior is exercised
- Call `listBillableMembers($when)`
- Assert returned `MemberIdList` contains both members only once

#### Test 2 — `listBillableMembers` excludes members whose item starts after the query date
- Create a `billable_item_instance` with `start_date > $when`
- Assert that member is excluded

#### Test 3 — `listBillableMembers` excludes ended items
- Create a `billable_item_instance` with `end_date <= $when`
- Assert that member is excluded

#### Test 4 — `listBillableItemsForMember` returns mapped Domain billable items
- Create an active `billable_item_instance` and linked `billable_items` row
- Call `listBillableItemsForMember($when, MemberId::create($member->id))`
- Assert returned `BillableItemList` contains an item with:
  - correct `BillableItemId`
  - correct `CompoundPrice`
  - `quantity = 1.0`
  - correct `description`

#### Test 5 — `listBillableItemsForMember` excludes items already invoiced within the bill cycle window
- Create active instance with bill cycle (e.g. monthly / annually)
- Create invoice + invoice line for same member and same billable item
- Set invoice date so it falls inside the exclusion window implied by:
  - `DATE_TRUNC('month', invoices.date)`
  - `billable_item_instances.bill_cycle_in_months`
- Assert repository no longer returns that billable item

#### Test 6 — `listBillableItemsForMember` includes items invoiced outside the bill cycle window
- Same fixture pattern as above
- Place invoice sufficiently far in the past
- Assert item is returned again

### Suggested fixture strategy
Use real factories and keep each test focused:
- `Member::factory()->createQuietly()`
- `BillableItem::factory()->create()`
- `BillableItemInstance::factory()->create()`
- `Invoice::factory()->forMember($member)->create()`
- create invoice lines through `$invoice->lines()->create([...])` or `InvoiceLine::factory()` if clearer

---

## 1.2 `app/Infrastructure/Invoices/Billing/BillableItemDbInstanceRepository.php`

### Recommendation
- **Test type:** Feature test additions
- **Existing file to update:** `tests/Feature/Infrastructure/Invoices/BillableItemDbInstanceRepositoryTest.php`

### Why feature test
This repository persists records and computes `bill_cycle_in_months` using DB-backed models. It is already covered partially, so this should be extended rather than split into a new test file.

### Source details
- `removeMany(...)` covered already
- `add(...)` covered already
- `ensure(...)` currently lacks direct coverage
  - method at `app/Infrastructure/Invoices/Billing/BillableItemDbInstanceRepository.php:42`

### Behaviors to add

#### Test 1 — `ensure` creates a record when no active record exists
- Create member and billable item
- Call `ensure(MemberId, BillableItemId)`
- Assert one row exists with:
  - correct `member_id`
  - correct `billable_item_id`
  - `start_date = now`
  - `end_date = null`
  - `bill_cycle_in_months` derived from `bill_period`

#### Test 2 — `ensure` does not duplicate an already active record
- Create an existing active `billable_item_instances` row for the same member/item
- Call `ensure(...)`
- Assert only one matching active record exists

#### Test 3 — `ensure` creates a new record when only ended rows exist
- Create a historical instance with `end_date` filled
- Call `ensure(...)`
- Assert a new active row is created

### Helper files needed
- None

---

## 1.3 `app/Infrastructure/Members/MembershipDbRepository.php`

### Recommendation
- **Test type:** Feature test additions
- **Existing file to update:** `tests/Feature/Infrastructure/Members/MembershipDbRepositoryTest.php`

### Why feature test
This repository maps Eloquent models into Domain objects and uses `findOrFail`. That is classic infrastructure integration behavior.

### Source details
- `getById(...)` at `app/Infrastructure/Members/MembershipDbRepository.php:15`
- `all()` at `app/Infrastructure/Members/MembershipDbRepository.php:25`

Current coverage now exercises `all()`, `getById()`, and the not-found path.

### Behaviors to add

#### Test 1 — `getById` returns the expected Domain membership
- Create a membership model with a known `billable_item_id`
- Call `getById(MembershipId::create($model->id))`
- Assert:
  - returned `Membership->id->value === $model->id`
  - returned `Membership->billableItemId->value === $model->billable_item_id`

#### Test 2 — `getById` throws when the membership does not exist
- Call `getById(MembershipId::create(999999))`
- Assert `ModelNotFoundException`

### Helper files needed
- None

---

## 1.4 `app/Infrastructure/Members/MemberDbRepository.php`

### Recommendation
- **Test type:** Feature test addition to existing file
- **Existing file to update:** `tests/Feature/Infrastructure/Members/MemberDbRepositoryTest.php`

### Why feature test
This class is already covered as an infrastructure repository; one field mapping remains unasserted.

### Source details
- `getById(...)` at `app/Infrastructure/Members/MemberDbRepository.php:15`

### Behavior now covered
- The existing happy-path test now asserts `isVolunteer` mapping as well

### Helper files needed
- None

---

## 2. Current Domain status

At this point, there are **no strong remaining Domain test candidates** that justify new test files.

The following Domain files are already meaningfully covered:
- `NumericId`
- `ApplyMembershipBilling`
- `ApplyMemberVolunteerBilling`
- `BillPeriod`
- `BillableItemId`
- `BillableItemIdList`
- `BillableItemList`
- `CompoundPrice`
- `InvoiceBatchGeneratorImpl`
- `InvoiceBatchId`
- `InvoiceGeneratorImpl`
- `InvoiceId`
- `InvoiceNumber`
- `InvoiceNumberGeneratorImpl`
- `ExtraMembershipItemCode`
- `Gender`
- `MemberId`
- `MemberIdList`
- `MembershipId`
- `MembershipList`

---

## 3. Files intentionally not worth direct tests

These should remain untested directly unless they gain logic later.

### Interfaces only
- `app/Domain/Invoices/Billing/ApplyBillableItem.php`
- `app/Domain/Invoices/Billing/BillableItemInstanceRepository.php`
- `app/Domain/Invoices/Billing/BillableItemsViewRepository.php`
- `app/Domain/Invoices/CreateInvoice.php`
- `app/Domain/Invoices/InvoiceBatchGenerator.php`
- `app/Domain/Invoices/InvoiceGenerator.php`
- `app/Domain/Invoices/InvoiceNumberGenerator.php`
- `app/Domain/Invoices/InvoiceRepository.php`
- `app/Domain/Members/ExtraMembershipBillingItemRepository.php`
- `app/Domain/Members/MemberRepository.php`
- `app/Domain/Members/MembershipRepository.php`

### Pure DTO/entity holders with no behavior
- `app/Domain/Invoices/Billing/BillableItem.php`
- `app/Domain/Invoices/GenerateInvoice.php`
- `app/Domain/Invoices/InvoiceBatch.php`
- `app/Domain/Invoices/NewInvoice.php`
- `app/Domain/Members/Member.php`
- `app/Domain/Members/Membership.php`

---

## 4. Helper file assessment

### New Expectation classes
None required.

Existing unit helpers are sufficient:
- `tests/Unit/Domain/Invoices/BillableItemRepositoryExpectation.php`
- `tests/Unit/Domain/Invoices/BillableItemsViewRepositoryExpectation.php`
- `tests/Unit/Domain/Invoices/CreateInvoiceExpectation.php`
- `tests/Unit/Domain/Invoices/InvoiceGeneratorExpectation.php`
- `tests/Unit/Domain/Invoices/InvoiceRepositoryExpectation.php`
- `tests/Unit/Domain/Members/ExtraMembershipBillingItemRepositoryExpectation.php`
- `tests/Unit/Domain/Members/MemberRepositoryExpectation.php`
- `tests/Unit/Domain/Members/MembershipRepositoryExpectation.php`

### Builders / fixture helpers
Not required for the current remaining scope.

Optional only for readability if `BillableItemsViewDbRepositoryTest` becomes large:
- `tests/Feature/Infrastructure/Invoices/Builders/BillableItemsViewFixtureBuilder.php`

Do **not** create this unless the repository test becomes repetitive enough to justify it.

---

## 5. Implementation order

1. **Add feature coverage for `BillableItemsViewDbRepository`**
   - create `tests/Feature/Infrastructure/Invoices/BillableItemsViewDbRepositoryTest.php`
   - cover active/ended/future-start filtering and invoice-window exclusion

2. **Extend `BillableItemDbInstanceRepositoryTest`**
   - add `ensure()` creation and no-duplication coverage

3. **Extend `MembershipDbRepositoryTest`**
   - add `getById()` happy path
   - add `getById()` not-found path

4. **Extend `MemberDbRepositoryTest`**
   - assert `isVolunteer` mapping in happy path

---

## 6. Expected output files to modify/create

### Create
- `tests/Feature/Infrastructure/Invoices/BillableItemsViewDbRepositoryTest.php`

### Update
- `tests/Feature/Infrastructure/Invoices/BillableItemDbInstanceRepositoryTest.php`
- `tests/Feature/Infrastructure/Members/MembershipDbRepositoryTest.php`
- `tests/Feature/Infrastructure/Members/MemberDbRepositoryTest.php`
