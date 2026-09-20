# Member Lifecycle Scenario Tests

## Overview

**Date:** 2026-09-20

Add an organized, reusable harness for writing *lifetime* feature tests of a member's billing and invoicing journey. A single scenario test drives a member through: **public registration**, admin login, editing, joining activities, and several invoice generations spread across months — asserting the billing engine reacts correctly at every step. The goal is that a new scenario is just a new test method (or test class) that reads like a timeline.

Design decisions (from the user):

- **Members are created via the public registration flow** (`App\Http\Controllers\Registration\RegistrationController`), not via Filament `CreateMember`.
- Admin actions (attach activity, edit member, generate invoice) run through the **full Filament Livewire UI**.
- Billing fixtures come from the **real seeders** (`MembershipSeeder` + `ActivitySeeder`).

---

## Current situation

### Public registration creates the member

`app/Http/Controllers/Registration/RegistrationController.php` is a five-step session wizard (`routes/web.php`):

1. `POST register.welcome`
2. `POST register.membership` — collects `windsurfing_lessons`, `rtc_lessons`, `club_access`, `storage` booleans (`StoreMembershipRequest` → `MembershipData`). At least one must be selected.
3. `POST register.personal-information` — name, email, gender, birthdate, address (`StorePersonalInformationRequest` → `PersonalInfoData`).
4. `POST register.payment-information` — IBAN/BIC/holder + `mandate_accepted` (`StorePaymentInformationRequest` → `PaymentInfoData`; `mandateAcceptedDate` is set to a new `DateTimeImmutable`).
5. `POST register.confirmation` — `confirm_data_correct=1`, `confirm_membership=1` → `NewMemberService::fromRegistration($formData)`.

`NewMemberService::fromRegistration` (via `MemberDbRepository::newMember`) creates the `Member` (with the **default** membership from `MembershipRepository::getDefault()`, `is_volunteer = false`) plus its `PaymentInformation`. `MemberObserver::created` then fires and applies membership/volunteer/household billing.

Important nuance: the `windsurfing_lessons`/`rtc_lessons`/`club_access`/`storage` booleans are only stored in `registration_data` and dispatched in the `NewMemberRegistration` event (for welcome/admin emails). They do **not** attach activities or create activity billing instances. Activities are attached afterwards by the admin.

The full flow is already exercised by `tests/Feature/Http/Controllers/Registration/RegistrationControllerTest.php` (`test_full_registration_flow_end_to_end`), including the exact request payloads.

### Billing is event-driven, not imperative

- `app/Observers/MemberObserver.php` — `created` runs `ApplyMembershipBilling` (age-appropriate membership `BillableItemInstance`), `ApplyMemberVolunteerBilling` (contribution, and restitution only if `is_volunteer`), `ApplySameHouseholdBilling`. `updated` re-applies only on changed `membership_id`, `is_volunteer`, or `household_id`.
- `app/Observers/ActivityMemberObserver.php` — `created` runs `ApplyActivityBilling` (activity `BillableItemInstance`, end date = activity `end_date`) and links it on the `activity_member` pivot.

### Invoicing reads billable instances at a point in time

- `app/Domain/Invoices/InvoiceGeneratorImpl.php` → `BillableItemsViewRepository::listBillableItemsForMember($when, $memberId)` → `InvoiceRepository::applyLines(...)`.
- `BillableItemsViewDbRepository` filters by `start_date <= $when` and active `end_date`, excludes items already billed in the current `bill_month`-anchored window, and computes pro-rated `quantity` via `BillableItemInstance::quantityFor($when)` (see `20260814_bill-month-proration.md`).
- `InvoiceRepositoryDb::applyLines()` uses `firstOrCreate` on an **Open invoice whose `date` is in the same month** as `$when`. **Consequence:** generating twice in one calendar month merges lines into the same invoice. Scenarios place each invoice step in a distinct month.

### Admin UI actions used by the harness

- Attach an activity: `ActivitiesRelationManager` (`AttachAction`, field `recordId`).
- Edit a member: `EditMember` (`fillForm` merges into existing state).
- Generate an invoice for a member: `InvoicesRelationManager::generate` header action → `InvoiceGenerator::generate(new InvoiceTarget(memberId, CarbonImmutable::now()))`. This is the UI path for per-member invoice generation (the monthly `InvoiceBatchGenerator` is console-only).

### Time control

- Tests use `CarbonImmutable::setTestNow(...)`; `ClockInterface` is bound to `Carbon\FactoryImmutable`, so `setTestNow` drives `InvoiceNumberGeneratorImpl`'s year, member `age`, and `CarbonImmutable::now()` used by the `generate` action and the billing repositories.
- Caveat: `StorePaymentInformationRequest::toPaymentInfoData()` uses a native `new DateTimeImmutable()` for `mandateAcceptedDate`, so that one value is the real clock — irrelevant to the billing assertions.

### Conventions

- Feature tests extend `Tests\FeatureTestCase` (`LazilyRefreshDatabase`).
- Admin login: `Tests\Concerns\WithAuthorizedUser::withAuthorizedUser()`.
- Relation managers tested via `Livewire::test(RelationManager::class, ['ownerRecord' => $model, 'pageClass' => SomePage::class])` (see `tests/Feature/Filament/BankTransaction/BankTransactionResourceTest.php`).
- Commands run through `./Taskfile artisan ...`.

---

## Planned changes

1. **New trait** `tests/Concerns/TestsMemberLifecycle.php` — fluent helpers: `registerMember()` (public HTTP wizard), `joinActivity()`, `updateMember()`, `generateInvoiceFor()`, `travelTo()`, plus small assertion conveniences.
2. **New abstract base class** `tests/Feature/Scenarios/MemberLifecycleScenario.php` — wires `WithAuthorizedUser` + `TestsMemberLifecycle`, seeds fixtures, logs in as admin, resets the clock in `tearDown`.
3. **First scenario** `tests/Feature/Scenarios/WindsurferLifetimeScenarioTest.php` — the worked example.

Each later scenario is a new `final class XxxScenarioTest extends MemberLifecycleScenario`.

---

## Detailed plan

### Trait: `tests/Concerns/TestsMemberLifecycle.php`

```php
<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Domain\Members\Gender;
use App\Filament\Admin\Resources\Members\Pages\EditMember;
use App\Filament\Admin\Resources\Members\RelationManagers\ActivitiesRelationManager;
use App\Filament\Admin\Resources\Members\RelationManagers\InvoicesRelationManager;
use App\Models\Activity;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\Membership;
use Carbon\CarbonImmutable;
use Database\Seeders\ActivitySeeder;
use Database\Seeders\CostCenterSeeder;
use Database\Seeders\MembershipSeeder;
use Livewire\Livewire;

trait TestsMemberLifecycle
{
    protected function seedBillingFixtures(): void
    {
        $this->seed(CostCenterSeeder::class);
        $this->seed(ActivitySeeder::class);
        $this->seed(MembershipSeeder::class);
    }

    protected function travelTo(string $date): CarbonImmutable
    {
        $now = CarbonImmutable::parse($date);

        CarbonImmutable::setTestNow($now);

        return $now;
    }

    protected function resetTime(): void
    {
        CarbonImmutable::setTestNow();
    }

    protected function defaultMembership(): Membership
    {
        return Membership::query()->where('is_default', true)->firstOrFail();
    }

    protected function activityNamed(string $name): Activity
    {
        return Activity::query()->where('name', $name)->firstOrFail();
    }

    /**
     * Register a new member through the public five-step registration wizard.
     *
     * @param array<string, mixed> $personalInfo overrides for the personal-information step
     * @param array<string, bool>  $activities   selections for the membership step
     */
    protected function registerMember(array $personalInfo = [], array $activities = ['windsurfing_lessons' => true]): Member
    {
        $email = $personalInfo['email'] ?? fake()->unique()->safeEmail();

        $personalData = [
            'first_name' => 'Jan',
            'last_name' => 'Vries',
            'email' => $email,
            'gender' => Gender::Male->value,
            'birthdate' => '1990-01-15',
            'address_street' => 'Surfstrand',
            'address_housenumber' => '2',
            'address_postalcode' => '1324CT',
            'address_city' => 'Almere',
            ...$personalInfo,
        ];

        $paymentData = [
            'banking_account_number' => 'NL91ABNA0417164300',
            'banking_bic' => 'ABNANL2A',
            'banking_account_holder_name' => 'J. de Vries',
            'mandate_accepted' => '1',
        ];

        $this->post(route('register.welcome'))
            ->assertRedirect(route('register.membership'));

        $this->post(route('register.membership'), $activities)
            ->assertRedirect(route('register.personal-information'));

        $this->post(route('register.personal-information'), $personalData)
            ->assertRedirect(route('register.payment-information'));

        $this->post(route('register.payment-information'), $paymentData)
            ->assertRedirect(route('register.confirmation'));

        $this->post(route('register.confirmation'), [
            'confirm_data_correct' => '1',
            'confirm_membership' => '1',
        ])->assertRedirect(route('register.success'));

        return Member::query()->where('email', $email)->firstOrFail();
    }

    protected function joinActivity(Member $member, Activity $activity): void
    {
        Livewire::test(ActivitiesRelationManager::class, [
            'ownerRecord' => $member,
            'pageClass' => EditMember::class,
        ])
            ->callTableAction('attach', data: ['recordId' => $activity->id])
            ->assertHasNoTableActionErrors();
    }

    /**
     * @param array<string, mixed> $changes
     */
    protected function updateMember(Member $member, array $changes): Member
    {
        Livewire::test(EditMember::class, ['record' => $member->getRouteKey()])
            ->fillForm($changes)
            ->call('save')
            ->assertHasNoFormErrors();

        return $member->refresh();
    }

    protected function generateInvoiceFor(Member $member): void
    {
        Livewire::test(InvoicesRelationManager::class, [
            'ownerRecord' => $member,
            'pageClass' => EditMember::class,
        ])->callAction('generate');
    }

    protected function invoiceFor(Member $member, string $date): Invoice
    {
        $month = CarbonImmutable::parse($date)->startOfMonth();

        return Invoice::query()
            ->where('member_id', $member->id)
            ->whereBetween('date', [$month, $month->copy()->endOfMonth()])
            ->firstOrFail();
    }

    protected function assertInvoiceLine(Invoice $invoice, string $description, float $price, float $quantity): void
    {
        $invoiceLine = $invoice->lines()->where('description', $description)->first();

        static::assertNotNull($invoiceLine, "Expected invoice line for '{$description}'");
        static::assertEqualsWithDelta($price, (float) $invoiceLine->price, 0.001);
        static::assertEqualsWithDelta($quantity, (float) $invoiceLine->quantity, 0.001);
    }
}
```

Notes:

- `registerMember()` mirrors `RegistrationControllerTest::test_full_registration_flow_end_to_end`; `birthdate 1990-01-15` guarantees an adult (age >= 18) so the adult membership item is selected.
- Registration always assigns the **default** membership and `is_volunteer = false`; scenarios that need a different membership or volunteer status change it afterwards with `updateMember()`.
- `fillForm()` merges, so `updateMember()` only passes changed fields.
- `AttachAction` field is `recordId`; the `generate` action's redirect is a stored Livewire effect (does not throw).

### Base class: `tests/Feature/Scenarios/MemberLifecycleScenario.php`

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Scenarios;

use Tests\Concerns\TestsMemberLifecycle;
use Tests\Concerns\WithAuthorizedUser;
use Tests\FeatureTestCase;

abstract class MemberLifecycleScenario extends FeatureTestCase
{
    use TestsMemberLifecycle;
    use WithAuthorizedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedBillingFixtures();
        $this->withAuthorizedUser();
    }

    protected function tearDown(): void
    {
        $this->resetTime();

        parent::tearDown();
    }
}
```

`withAuthorizedUser()` sets `actingAs` for the whole test, so the public registration `post()` calls run fine (registration routes are unauthenticated) and the Filament steps run as a fully-authorized admin.

### Scenario: `tests/Feature/Scenarios/WindsurferLifetimeScenarioTest.php`

Seeded amounts (from `MembershipSeeder` and `ActivitySeeder`):

| Item | price | vat | bill_period | bill_month |
|---|---|---|---|---|
| Lidmaatschap Windsurfer (adult) | 68.00 | 14.28 | annually | 1 |
| Vrijwilligersbijdrage (contribution) | 20.00 | 4.20 | annually | 1 |
| Vrijwilligersbijdrage restitutie | -22.00 | -4.62 | annually | 1 |
| Activiteit: Reguliere les volwassenen | 37.00 | 7.77 | monthly | 1 (ends 2026-10-30) |

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Scenarios;

use App\Domain\Members\ExtraMembershipItemCode;
use App\Models\ExtraMembershipItem;
use App\Models\Invoice;
use Override;

final class WindsurferLifetimeScenarioTest extends MemberLifecycleScenario
{
    #[Override]
    public function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-08-15');
    }

    public function test_a_windsurfer_is_billed_over_their_lifetime(): void
    {
        // Step 1 — the member registers through the public wizard (default Windsurfer membership, not a volunteer).
        $member = $this->registerMember();

        $this->assertDatabaseHas('payment_information', [
            'member_id' => $member->id,
            'banking_account_number' => 'NL91ABNA0417164300',
        ]);

        $this->assertDatabaseHas('billable_item_instances', [
            'member_id' => $member->id,
            'billable_item_id' => $member->membership->adultBillableItem->id,
            'start_date' => '2026-08-15',
            'end_date' => null,
        ]);

        // Step 2 — admin adds them to the adult lessons activity.
        $this->joinActivity($member, $this->activityNamed('Reguliere les volwassenen'));

        $this->assertDatabaseHas('billable_item_instances', [
            'member_id' => $member->id,
            'billable_item_id' => $this->activityNamed('Reguliere les volwassenen')->billable_item_id,
            'end_date' => '2026-10-30',
        ]);

        // Step 3 — first invoice: membership + contribution are pro-rated (5/12), activity is monthly.
        $this->generateInvoiceFor($member);

        $firstInvoice = $this->invoiceFor($member, '2026-08-15');
        $this->assertSame(3, $firstInvoice->lines()->count());
        $this->assertInvoiceLine($firstInvoice, 'Lidmaatschap Windsurfer (volwassenen)', 68.00, 5 / 12);
        $this->assertInvoiceLine($firstInvoice, 'Vrijwilligersbijdrage', 20.00, 5 / 12);
        $this->assertInvoiceLine($firstInvoice, 'Activiteit: Reguliere les volwassenen', 37.00, 1.0);

        // Step 4 — a little over a month later: only the monthly activity re-bills.
        $this->travelTo('2026-09-20');
        $this->generateInvoiceFor($member);

        $secondInvoice = $this->invoiceFor($member, '2026-09-20');
        $this->assertSame(1, $secondInvoice->lines()->count());
        $this->assertInvoiceLine($secondInvoice, 'Activiteit: Reguliere les volwassenen', 37.00, 1.0);

        // Step 5 — a week later the member becomes a volunteer (adds a restitution credit).
        $this->travelTo('2026-09-27');
        $this->updateMember($member, ['is_volunteer' => true]);

        $restitutionBillableItemId = ExtraMembershipItem::query()
            ->where('code', ExtraMembershipItemCode::VolunteerRestitution)
            ->firstOrFail()
            ->billable_item_id;

        $this->assertDatabaseHas('billable_item_instances', [
            'member_id' => $member->id,
            'billable_item_id' => $restitutionBillableItemId,
            'start_date' => '2026-09-27',
        ]);

        // Step 6 — third invoice (next month): activity again + pro-rated restitution.
        $this->travelTo('2026-10-15');
        $this->generateInvoiceFor($member);

        $thirdInvoice = $this->invoiceFor($member, '2026-10-15');
        $this->assertSame(2, $thirdInvoice->lines()->count());
        $this->assertInvoiceLine($thirdInvoice, 'Activiteit: Reguliere les volwassenen', 37.00, 1.0);
        $this->assertInvoiceLine($thirdInvoice, 'Vrijwilligersbijdrage restitutie', -22.00, 4 / 12);

        // Step 7 — fourth invoice in January: annual items re-bill at full quantity, activity has ended.
        $this->travelTo('2027-01-10');
        $this->generateInvoiceFor($member);

        $fourthInvoice = $this->invoiceFor($member, '2027-01-10');
        $this->assertSame(3, $fourthInvoice->lines()->count());
        $this->assertInvoiceLine($fourthInvoice, 'Lidmaatschap Windsurfer (volwassenen)', 68.00, 1.0);
        $this->assertInvoiceLine($fourthInvoice, 'Vrijwilligersbijdrage', 20.00, 1.0);
        $this->assertInvoiceLine($fourthInvoice, 'Vrijwilligersbijdrage restitutie', -22.00, 1.0);

        $this->assertDatabaseMissing('invoice_lines', [
            'invoice_id' => $fourthInvoice->id,
            'description' => 'Activiteit: Reguliere les volwassenen',
        ]);

        $this->assertSame(4, Invoice::query()->where('member_id', $member->id)->count());
    }
}
```

**Pro-ration reference** (`BillableItemInstance::quantityFor`):

- August join, annual `bill_month = 1`: offset `(8 - 1 + 12) % 12 % 12 = 7`, anchor Jan 2026, next bill Jan 2027, coverage Aug 2026 → `5/12`.
- Restitution started 2026-09-27, invoiced 2026-10-15: coverage Sep 2026 → `4/12`.

---

## Additional scenarios (pattern to follow)

Each new scenario is a class extending `MemberLifecycleScenario`, exercising a different billing applicator:

- **Youngster joins a household** — `registerMember(['birthdate' => '2015-01-01'])` (kids membership item); then `updateMember($member, ['household_id' => $household->id])` triggers `ApplySameHouseholdBilling`.
- **Membership change mid-cycle** — register, then `updateMember($member, ['membership' => $zeiler->id])` stops the old instance and starts the new one; the next invoice reflects the switch.
- **Activity detach** — `joinActivity()` then detach via `ActivitiesRelationManager` `DetachAction`; `ActivityMemberObserver::deleted` stops the instance, so the next invoice omits it.
- **Nothing to invoice** — after the activity ends and annual items are billed, `generate` produces no new invoice.

No harness changes are needed for these.

---

## Testing

```
./Taskfile artisan test --compact tests/Feature/Scenarios/WindsurferLifetimeScenarioTest.php
./Taskfile artisan test --compact --filter=test_a_windsurfer_is_billed_over_their_lifetime
```

Scenarios seed fixtures in `setUp` (inside `LazilyRefreshDatabase`), so no other test files are affected.

---

## Key design decisions

| Decision | Rationale |
|---|---|
| Trait + abstract base class, not a single test | "Easy to add scenarios" = new `final class ... extends MemberLifecycleScenario`. The trait keeps step primitives reusable. |
| Member creation via public registration wizard | Matches the requirement; exercises `RegistrationController` → `NewMemberService` → `MemberDbRepository::newMember` and `MemberObserver::created` end-to-end. |
| Admin steps via existing Filament pages/relation managers | Attach activity, edit member, and generate invoice run through the real UI, firing observers from real `save`/`attach` calls. |
| Real seeders for fixtures | Assertion prices/descriptions match production data. |
| Time control via `CarbonImmutable::setTestNow` | Existing convention; drives `ClockInterface` (bound to `FactoryImmutable`). |
| One invoice per month constraint surfaced | `applyLines` uses `firstOrCreate` within a month; scenarios place invoice steps in distinct months. |

## Open questions / notes

- Registration's `windsurfing_lessons`/`rtc_lessons`/`club_access`/`storage` selections do not create activity billing; activities are attached by the admin afterwards. If a future scenario needs the registration selections to drive activity billing, that is a separate product change.
- `StorePaymentInformationRequest` sets `mandateAcceptedDate` with a native `DateTimeImmutable()` (real clock), not Carbon's test now — irrelevant to the billing assertions in these scenarios.
- The `generate` action's trailing `redirect()` is a no-op in tests (stored effect, no throw).
- If a scenario needs the monthly *batch* flow (`InvoiceBatchGenerator`), it is console-only today and out of scope for this plan.
