# Registration Flow: Personal Information, Payment Information & Tests

## Overview

This plan covers three major work items:
1. **Personal Information step** - Complete the registration step 3 (personal info) with all Member model fields
2. **Payment Information step** - New `PaymentInformation` model with 1:1 relation to Member, plus registration step 4
3. **Tests** - Unit tests for FormData/domain classes, feature tests for the full registration flow

---

## Part 1: Personal Information Step

### 1.1 Create `PersonalInfoData` Value Object

**File:** `app/Domain/Registration/PersonalInfoData.php`

Follow the exact pattern of `MembershipData` (`app/Domain/Registration/MembershipData.php`):

```php
<?php

declare(strict_types=1);

namespace App\Domain\Registration;

/**
 * @phpstan-type PersonalInfoDataArray array{
 *     firstName?: string,
 *     infixName?: string,
 *     lastName?: string,
 *     email?: string,
 *     gender?: string,
 *     birthdate?: string,
 *     addressStreet?: string,
 *     addressHousenumber?: string,
 *     addressHousenumberAddition?: string,
 *     addressPostalcode?: string,
 *     addressCity?: string,
 * }
 */

final class PersonalInfoData
{
    public function __construct(
        public string $firstName,
        public string $infixName,
        public string $lastName,
        public string $email,
        public string $gender,
        public string $birthdate,
        public string $addressStreet,
        public string $addressHousenumber,
        public string $addressHousenumberAddition,
        public string $addressPostalcode,
        public string $addressCity,
    ) {
    }

    public static function createDefault(): self
    {
        return new self(
            firstName: '',
            infixName: '',
            lastName: '',
            email: '',
            gender: '',
            birthdate: '',
            addressStreet: '',
            addressHousenumber: '',
            addressHousenumberAddition: '',
            addressPostalcode: '',
            addressCity: '',
        );
    }

    /** @param PersonalInfoDataArray $data */
    public static function createFromArray(array $data): self
    {
        return new self(
            firstName: $data['firstName'] ?? '',
            infixName: $data['infixName'] ?? '',
            lastName: $data['lastName'] ?? '',
            email: $data['email'] ?? '',
            gender: $data['gender'] ?? '',
            birthdate: $data['birthdate'] ?? '',
            addressStreet: $data['addressStreet'] ?? '',
            addressHousenumber: $data['addressHousenumber'] ?? '',
            addressHousenumberAddition: $data['addressHousenumberAddition'] ?? '',
            addressPostalcode: $data['addressPostalcode'] ?? '',
            addressCity: $data['addressCity'] ?? '',
        );
    }

    /** @return PersonalInfoDataArray */
    public function toArray(): array
    {
        return [
            'firstName' => $this->firstName,
            'infixName' => $this->infixName,
            'lastName' => $this->lastName,
            'email' => $this->email,
            'gender' => $this->gender,
            'birthdate' => $this->birthdate,
            'addressStreet' => $this->addressStreet,
            'addressHousenumber' => $this->addressHousenumber,
            'addressHousenumberAddition' => $this->addressHousenumberAddition,
            'addressPostalcode' => $this->addressPostalcode,
            'addressCity' => $this->addressCity,
        ];
    }
}
```

**Key decisions:**
- All fields are strings (including gender and birthdate) since FormData is a session DTO, not a model
- Gender stores the `Gender` enum value string (M, F, NB, U, O)
- Birthdate stores a date string (format: `Y-m-d`)
- Fields map to the `members` table columns: `first_name`, `infix_name`, `last_name`, `email`, `gender`, `birthdate`, `address_street`, `address_housenumber`, `address_housenumber_addition`, `address_postalcode`, `address_city`
- `household_id`, `membership_id`, `is_volunteer`, `user_id` are NOT included (not user-input during registration)

### 1.2 Update `FormData` to Include `PersonalInfoData`

**File:** `app/Domain/Registration/FormData.php`

Changes:
1. Add `PersonalInfoData` import
2. Add `public PersonalInfoData $personalInfo` constructor parameter
3. Update `@phpstan-type FormDataArray` to include `personalInfo?: PersonalInfoDataArray`
4. Import the `PersonalInfoDataArray` phpstan type
5. Update `create()` to hydrate `personalInfo` from array
6. Update `createDefault()` to include `PersonalInfoData::createDefault()`
7. Add `personalInfo(PersonalInfoData $personalInfo): self` method (same pattern as `membership()`)
8. Update `toArray()` to include `'personalInfo' => $this->personalInfo->toArray()`
9. Update `welcome()` and `membership()` to pass through `$this->personalInfo`

### 1.3 Create `StorePersonalInformationRequest`

**File:** `app/Http/Requests/Registration/StorePersonalInformationRequest.php`

Follow the pattern of `StoreMembershipRequest`:

```php
final class StorePersonalInformationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'infix_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'gender' => ['required', 'string', Rule::in(array_column(Gender::cases(), 'value'))],
            'birthdate' => ['required', 'date', 'before:today'],
            'address_street' => ['required', 'string', 'max:255'],
            'address_housenumber' => ['required', 'string', 'max:20'],
            'address_housenumber_addition' => ['nullable', 'string', 'max:20'],
            'address_postalcode' => ['required', 'string', 'regex:/^\d{4}[A-Z]{2}$/'],
            'address_city' => ['required', 'string', 'max:255'],
        ];
    }

    public function toPersonalInfoData(): PersonalInfoData
    {
        return new PersonalInfoData(
            firstName: (string) $this->string('first_name'),
            infixName: (string) ($this->string('infix_name') ?? ''),
            lastName: (string) $this->string('last_name'),
            email: (string) $this->string('email'),
            gender: (string) $this->string('gender'),
            birthdate: (string) $this->string('birthdate'),
            addressStreet: (string) $this->string('address_street'),
            addressHousenumber: (string) $this->string('address_housenumber'),
            addressHousenumberAddition: (string) ($this->string('address_housenumber_addition') ?? ''),
            addressPostalcode: (string) $this->string('address_postalcode'),
            addressCity: (string) $this->string('address_city'),
        );
    }
}
```

### 1.4 Update the Personal Information Blade View

**File:** `resources/views/pages/register/3-personal-information.blade.php`

The current view is a stub. Rewrite it to match the pattern of `2-membership-information.blade.php`:

1. Add `@php use App\Domain\Registration\FormData; @endphp` and `@php /** @var FormData $formData */ @endphp` at top
2. Add `method="POST"` and `@csrf` to the `<form>` tag
3. Pass `$formData` from controller (update controller to pass it)
4. Add missing fields: `email`, `gender` (select/radio), `birthdate` (date input)
5. Fix field names to match the FormRequest: `address_street`, `address_housenumber`, `address_housenumber_addition`, `address_postalcode`, `address_city`
6. Bind values from `$formData->personalInfo` using the `:value` prop
7. Add `<x-molecule.form-buttons/>` at the bottom
8. Group fields logically with `<x-atoms.divider>`:
   - **Personal details**: initials, first_name, infix_name, last_name, email, gender, birthdate
   - **Address**: address_street, address_housenumber, address_housenumber_addition, address_postalcode, address_city

**New input components needed:**

Since no `<x-atoms.inputs.select>` or `<x-atoms.inputs.date>` exist, create them:

**File:** `resources/views/components/atoms/inputs/select.blade.php`
- Props: `name`, `options` (array of value => label), `value` (default '')
- Styled `<select>` matching the text input styling
- Uses `old($name, $value)` for selected state

**File:** `resources/views/components/atoms/inputs/date.blade.php`
- Props: `name`, `value` (default ''), `id`, `placeholder`
- Styled `<input type="date">` matching the text input styling
- Uses `old($name, $value)` for value

### 1.5 Update `RegistrationController`

**File:** `app/Http/Controllers/Registration/RegistrationController.php`

Add/modify methods:

1. **`showPersonalInformationForm()`** - Update to pass `$formData` to the view:
   ```php
   return view('pages.register.3-personal-information', compact('formData'));
   ```

2. **`savePersonalInformationForm(StorePersonalInformationRequest $request): RedirectResponse`** - New method:
   ```php
   public function savePersonalInformationForm(StorePersonalInformationRequest $request): RedirectResponse
   {
       $formData = $this->formDataRepository->get();
       $this->formDataRepository->save($formData->personalInfo($request->toPersonalInfoData()));

       return redirect()->route('register.payment-information');
   }
   ```

3. **`showPaymentInformationForm(): RedirectResponse | View`** - New method (step guard + render)
4. **`savePaymentInformationForm(StorePaymentInformationRequest $request): RedirectResponse`** - New method

### 1.6 Add Routes

**File:** `routes/web.php`

Add after the existing personal-information GET route:

```php
Route::post('/registratie/persoonlijke-informatie', [Controllers\Registration\RegistrationController::class, 'savePersonalInformationForm']);

Route::get('/registratie/betaal-informatie', [Controllers\Registration\RegistrationController::class, 'showPaymentInformationForm'])->name('register.payment-information');
Route::post('/registratie/betaal-informatie', [Controllers\Registration\RegistrationController::class, 'savePaymentInformationForm']);
```

---

## Part 2: Payment Information

### 2.1 Create `payment_information` Migration

**File:** `database/migrations/xxxx_xx_xx_xxxxxx_create_payment_information_table.php`

Create via: `./Taskfile artisan make:migration create_payment_information_table`

```php
Schema::create('payment_information', function (Blueprint $table) {
    $table->id();
    $table->uuid('uuid')->unique();
    $table->foreignId('member_id')->unique()->constrained()->cascadeOnDelete();
    $table->string('banking_account_number'); // IBAN
    $table->string('banking_bic');
    $table->string('banking_account_holder_name');
    $table->date('mandate_accepted_date');
    $table->timestamps();
    $table->softDeletes();
});
```

**Key decisions:**
- `member_id` is unique (1:1 enforced at DB level) and cascadeOnDelete
- `uuid` is a unique UUID column, auto-generated on model creation (via `booted()` or observer)
- `mandate_accepted_date` is not nullable as it must be accepted when providing payment info
- `banking_account_number` stores IBAN (string, not integer)
- `banking_bic` stores the BIC/SWIFT code
- Table name: `payment_information` (singular concept, like `membership`)

### 2.2 Create `PaymentInformation` Model

**File:** `app/Models/PaymentInformation.php`

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Guarded('id', 'uuid', 'updated_at', 'created_at')]
final class PaymentInformation extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'payment_information';

    protected static function booted(): void
    {
        static::creating(function (PaymentInformation $paymentInformation) {
            if (empty($paymentInformation->uuid)) {
                $paymentInformation->uuid = (string) Str::uuid();
            }
        });
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'mandate_accepted_date' => 'date',
        ];
    }
}
```

### 2.3 Add `paymentInformation()` Relation to `Member` Model

**File:** `app/Models/Member.php`

Add:
```php
/** @return HasOne<PaymentInformation, $this> */
public function paymentInformation(): HasOne
{
    return $this->hasOne(PaymentInformation::class);
}
```

Import `Illuminate\Database\Eloquent\Relations\HasOne`.

### 2.4 Create `PaymentInformationFactory`

**File:** `database/factories/PaymentInformationFactory.php`

Create via: `./Taskfile artisan make:factory PaymentInformationFactory`

```php
public function definition(): array
{
    return [
        'member_id' => Member::factory(),
        'banking_account_number' => 'NL91ABNA0417164300',
        'banking_bic' => 'ABNANL2A',
        'banking_account_holder_name' => $this->faker->name(),
        'mandate_accepted_date' => $this->faker->date(),
    ];
}
```

### 2.5 Create `PaymentInfoData` Value Object

**File:** `app/Domain/Registration/PaymentInfoData.php`

Same pattern as `MembershipData` and `PersonalInfoData`:

```php
final class PaymentInfoData
{
    public function __construct(
        public string $bankingAccountNumber,
        public string $bankingBic,
        public string $bankingAccountHolderName,
        public DateTimeInterface $mandateAcceptedDate,
    ) {
    }

    // createDefault(), createFromArray(), toArray() following the same pattern
}
```

**Note:** `mandate_date` and `uuid` are NOT in the FormData — they are set when persisting to the model.

### 2.6 Update `FormData` to Include `PaymentInfoData`

**File:** `app/Domain/Registration/FormData.php`

Same changes as for `PersonalInfoData`:
1. Add `public PaymentInfoData $paymentInfo` constructor parameter
2. Update PHPStan types
3. Update `create()`, `createDefault()`, `toArray()`
4. Add `paymentInfo(PaymentInfoData $paymentInfo): self` method
5. Update all existing `with`-style methods to pass through `$this->paymentInfo`

### 2.7 Create `StorePaymentInformationRequest`

**File:** `app/Http/Requests/Registration/StorePaymentInformationRequest.php`

```php
final class StorePaymentInformationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'banking_account_number' => ['required', 'string', 'max:34'], // IBAN max length
            'banking_bic' => ['required', 'string', 'max:11'], // BIC max length
            'banking_account_holder_name' => ['required', 'string', 'max:255'],
            'mandate_accepted' => ['accepted'], // Must be true/1/yes/on
        ];
    }

    public function toPaymentInfoData(): PaymentInfoData
    {
        return new PaymentInfoData(
            bankingAccountNumber: (string) $this->string('banking_account_number'),
            bankingBic: (string) $this->string('banking_bic'),
            bankingAccountHolderName: (string) $this->string('banking_account_holder_name'),
            mandateAcceptedDate: new DateTimeImmutable(),
        );
    }
}
```

### 2.8 Create Payment Information Blade View

**File:** `resources/views/pages/register/4-payment-information.blade.php`

Follow the pattern of `2-membership-information.blade.php`:

```blade
@php use App\Domain\Registration\FormData; @endphp
@php /** @var FormData $formData */ @endphp
<x-layout.default title="{{__('titles.register')}}" subtitle="{{__('titles.payment_information') }}">
    <x-atoms.container>
        <form class="flex flex-col gap-4" method="POST">
            @csrf
            <p class="text-gray-700 text-sm/relaxed">
                {{ __('texts.register.payment_information.explainer') }}
            </p>

            <x-molecule.form-row label="{{ __('labels.banking_account_number') }}" name="banking_account_number">
                <x-atoms.inputs.text name="banking_account_number" :value="$formData->paymentInfo->bankingAccountNumber" placeholder="NL00BANK0123456789" />
            </x-molecule.form-row>

            <x-molecule.form-row label="{{ __('labels.banking_bic') }}" name="banking_bic">
                <x-atoms.inputs.text name="banking_bic" :value="$formData->paymentInfo->bankingBic" placeholder="ABNANL2A" />
            </x-molecule.form-row>

            <x-molecule.form-row label="{{ __('labels.banking_account_holder_name') }}" name="banking_account_holder_name">
                <x-atoms.inputs.text name="banking_account_holder_name" :value="$formData->paymentInfo->bankingAccountHolderName" placeholder="J. de Vries" />
            </x-molecule.form-row>

            <x-molecule.checkbox-row
                name="mandate_accepted"
                :value="$formData->paymentInfo->mandateAccepted"
                label="{{ __('labels.mandate_accepted') }}"
                description="{{ __('texts.register.payment_information.mandate_description') }}"
            />

            <x-molecule.form-buttons/>
        </form>
    </x-atoms.container>
</x-layout.default>
```

### 2.9 Update `RegistrationController` for Payment Step

Add to `RegistrationController`:

```php
public function showPaymentInformationForm(): RedirectResponse | View
{
    $formData = $this->formDataRepository->get();
    if ($formData->isStepDisallowed(Step::PaymentInfo)) {
        return redirect()->route('register.welcome');
    }

    return view('pages.register.4-payment-information', compact('formData'));
}

public function savePaymentInformationForm(StorePaymentInformationRequest $request): RedirectResponse
{
    $formData = $this->formDataRepository->get();
    $this->formDataRepository->save($formData->paymentInfo($request->toPaymentInfoData()));

    return redirect()->route('register.confirmation');
}
```

### 2.10 Add Language Strings

**File:** `lang/nl/labels.php` — Add:
```php
'banking_account_number' => 'IBAN rekeningnummer',
'banking_bic' => 'BIC code',
'banking_account_holder_name' => 'Ten name van',
'mandate_accepted' => 'Ik ga akkoord met de SEPA machtiging',
'payment_information' => 'Betaalgegevens',
'mandate_date' => 'Machtigingsdatum',
'uuid' => 'UUID',
```

**File:** `lang/nl/texts.php` — Add under `register`:
```php
'payment_information' => [
    'explainer' => 'Vul je bankgegevens in voor de automatische incasso van de contributie.',
    'mandate_description' => 'Door dit aan te vinken geef je toestemming voor automatische afschrijving via SEPA.',
],
```

**File:** `lang/nl/validation.php` — Add to `attributes` array:
```php
'banking_account_number' => 'IBAN rekeningnummer',
'banking_bic' => 'BIC code',
'banking_account_holder_name' => 'rekeninghouder',
'mandate_accepted' => 'SEPA machtiging',
'initials' => 'initialen',
'email' => 'e-mailadres',
'birthdate' => 'geboortedatum',
'address_street' => 'straat',
'address_housenumber' => 'huisnummer',
'address_housenumber_addition' => 'huisnummer toevoeging',
'address_postalcode' => 'postcode',
'address_city' => 'woonplaats',
```

---

## Part 3: Filament MemberResource — Payment Information Tab

### 3.1 Add Payment Information Tab to `MemberForm`

**File:** `app/Filament/Admin/Resources/Members/Schemas/MemberForm.php`

Add a 4th tab to the existing `Tabs` component:

```php
Tabs\Tab::make(__('labels.payment_information'))
    ->schema([
        TextInput::make('paymentInformation.banking_account_number')
            ->label(__('labels.banking_account_number'))
            ->maxLength(34),

        TextInput::make('paymentInformation.banking_bic')
            ->label(__('labels.banking_bic'))
            ->maxLength(11),

        TextInput::make('paymentInformation.banking_account_holder_name')
            ->label(__('labels.banking_account_holder_name'))
            ->maxLength(255),

        DatePicker::make('paymentInformation.mandate_date')
            ->format('d-m-Y')
            ->native(false)
            ->disabled()
            ->dehydrated(false)
            ->label(__('labels.mandate_accepted_date')),

        TextInput::make('paymentInformation.uuid')
            ->label(__('labels.uuid'))
            ->disabled()
            ->dehydrated(false),
    ]),
```

**Note:** Since `paymentInformation` is a `HasOne` relation, Filament can handle nested form fields using dot notation. However, for a `HasOne` relation to work with Filament's form, you may need to use `Section` or ensure the relation is loaded. Check if Filament v5 supports dot-notation for HasOne relations natively. If not, consider using a `RelationManager` approach instead.

**Alternative approach (if dot notation doesn't work for HasOne):** Create a `PaymentInformationRelationManager` following the existing pattern. However, since it's 1:1, a tab in the form is more appropriate. Filament v5 should support this via `Section::make()->relationship('paymentInformation')` or similar.

### 3.2 Ensure Member Form Saves Payment Information

In `EditMember.php`, the `mutateFormDataBeforeSave` may need updating to handle the nested payment information data. Alternatively, if using Filament's relationship handling, it should auto-save.

---

## Part 4: Tests

### 4.1 Unit Tests for `PersonalInfoData`

**File:** `tests/Unit/Domain/Registration/PersonalInfoDataTest.php`

Extends `Tests\UnitTestCase`.

Test cases:
- `test_create_default_returns_empty_values` — all fields are empty strings
- `test_create_from_array_hydrates_all_fields` — all fields populated from array
- `test_create_from_array_uses_defaults_for_missing_keys` — partial array fills defaults
- `test_to_array_returns_expected_structure` — roundtrip test

### 4.2 Unit Tests for `PaymentInfoData`

**File:** `tests/Unit/Domain/Registration/PaymentInfoDataTest.php`

Extends `Tests\UnitTestCase`.

Test cases:
- `test_create_default_returns_empty_values`
- `test_create_from_array_hydrates_all_fields`
- `test_create_from_array_uses_defaults_for_missing_keys`
- `test_to_array_returns_expected_structure`

### 4.3 Unit Tests for `FormData`

**File:** `tests/Unit/Domain/Registration/FormDataTest.php`

Extends `Tests\UnitTestCase`.

Test cases:
- `test_create_default_has_initial_step_and_empty_data` — step is Initial, membership/personalInfo/paymentInfo are defaults
- `test_create_from_array_hydrates_all_data` — full roundtrip
- `test_welcome_advances_step_to_welcome` — step goes from Initial to Welcome
- `test_membership_advances_step_and_stores_data` — step goes to Membership
- `test_personal_info_advances_step_and_stores_data` — step goes to PersonalInfo
- `test_payment_info_advances_step_and_stores_data` — step goes to PaymentInfo
- `test_step_does_not_go_backwards` — calling `welcome()` when already at Membership doesn't downgrade
- `test_is_step_disallowed_returns_true_when_skipping_steps` — e.g., at Welcome, PersonalInfo is disallowed
- `test_is_step_disallowed_returns_false_for_next_step` — at Welcome, Membership is allowed
- `test_to_array_and_create_roundtrip` — serialize then deserialize produces identical object

### 4.4 Unit Tests for `FormDataSessionRepository`

**File:** `tests/Feature/Infrastructure/Registration/FormDataSessionRepositoryTest.php`

Extends `Tests\FeatureTestCase` (needs session support).

Test cases:
- `test_get_returns_default_when_session_empty`
- `test_save_and_get_roundtrip` — save FormData, then get it back
- `test_save_persists_to_session` — verify session contains expected array

### 4.5 Feature Tests for Registration Flow

**File:** `tests/Feature/Registration/RegistrationFlowTest.php`

Extends `Tests\FeatureTestCase`.

Test cases:
- `test_welcome_page_renders` — GET `/registratie/welkom` returns 200
- `test_welcome_post_redirects_to_membership` — POST advances step
- `test_membership_page_renders_after_welcome` — GET `/registratie/activeiten` returns 200 after welcome
- `test_membership_page_redirects_to_welcome_without_prior_step` — step guard works
- `test_membership_post_validates_and_redirects_to_personal_info` — valid POST stores data
- `test_membership_post_validates_at_least_one_activity` — invalid POST returns errors
- `test_personal_information_page_renders_after_membership` — GET returns 200
- `test_personal_information_page_redirects_to_welcome_without_prior_step` — step guard
- `test_personal_information_post_validates_required_fields` — missing fields return errors
- `test_personal_information_post_validates_email_format` — invalid email returns error
- `test_personal_information_post_validates_postalcode_format` — invalid postalcode returns error
- `test_personal_information_post_validates_gender_value` — invalid gender returns error
- `test_personal_information_post_stores_data_and_redirects_to_payment` — valid POST
- `test_payment_information_page_renders_after_personal_info` — GET returns 200
- `test_payment_information_post_validates_required_fields` — missing fields return errors
- `test_payment_information_post_validates_mandate_accepted` — mandate not accepted returns error
- `test_payment_information_post_stores_data_and_redirects` — valid POST
- `test_full_registration_flow_end_to_end` — walk through all steps sequentially

**Testing approach:** Use `$this->get()` and `$this->post()` with session assertions. Since the FormData is stored in the session, use `$this->assertSessionHas('registration_form_data')` and inspect the stored array.

### 4.6 Feature Test for `PaymentInformation` Model

**File:** `tests/Feature/Models/PaymentInformationTest.php`

Extends `Tests\FeatureTestCase`.

Test cases:
- `test_uuid_is_generated_on_creation` — create a PaymentInformation, assert uuid is set and is a valid UUID
- `test_member_has_one_payment_information` — create Member with PaymentInformation, assert relation works
- `test_payment_information_belongs_to_member` — inverse relation test
- `test_member_id_is_unique` — creating two PaymentInformation for same member fails

---

## Implementation Order

Execute in this order to maintain a working state at each step:

### Phase 1: Domain Layer (no UI changes)
1. Create `PersonalInfoData` value object
2. Create `PaymentInfoData` value object
3. Update `FormData` to include both new data objects
4. Write unit tests for `PersonalInfoData`, `PaymentInfoData`, and updated `FormData`

### Phase 2: Database & Model
5. Create `payment_information` migration
6. Create `PaymentInformation` model with factory
7. Add `paymentInformation()` relation to `Member` model
8. Write model tests for `PaymentInformation`

### Phase 3: Personal Information Step
9. Create `StorePersonalInformationRequest`
10. Create `<x-atoms.inputs.select>` and `<x-atoms.inputs.date>` components
11. Rewrite `3-personal-information.blade.php` view
12. Update `RegistrationController` (showPersonalInformationForm + savePersonalInformationForm)
13. Add POST route for personal information
14. Add language strings
15. Write feature tests for personal information step

### Phase 4: Payment Information Step
16. Create `StorePaymentInformationRequest`
17. Create `4-payment-information.blade.php` view
18. Add `showPaymentInformationForm()` and `savePaymentInformationForm()` to controller
19. Add routes for payment information
20. Add language strings
21. Write feature tests for payment information step

### Phase 5: Filament Integration
22. Add Payment Information tab to `MemberForm`
23. Write Filament resource test for the new tab

### Phase 6: Full Flow Test
24. Write end-to-end registration flow test
25. Run full test suite: `./Taskfile artisan test --compact`

---

## Files Summary

### New Files
| File | Type |
|------|------|
| `app/Domain/Registration/PersonalInfoData.php` | Value Object |
| `app/Domain/Registration/PaymentInfoData.php` | Value Object |
| `app/Http/Requests/Registration/StorePersonalInformationRequest.php` | Form Request |
| `app/Http/Requests/Registration/StorePaymentInformationRequest.php` | Form Request |
| `app/Models/PaymentInformation.php` | Eloquent Model |
| `database/factories/PaymentInformationFactory.php` | Factory |
| `database/migrations/xxxx_create_payment_information_table.php` | Migration |
| `resources/views/components/atoms/inputs/select.blade.php` | Blade Component |
| `resources/views/components/atoms/inputs/date.blade.php` | Blade Component |
| `resources/views/pages/register/4-payment-information.blade.php` | Blade View |
| `tests/Unit/Domain/Registration/PersonalInfoDataTest.php` | Unit Test |
| `tests/Unit/Domain/Registration/PaymentInfoDataTest.php` | Unit Test |
| `tests/Unit/Domain/Registration/FormDataTest.php` | Unit Test |
| `tests/Feature/Infrastructure/Registration/FormDataSessionRepositoryTest.php` | Feature Test |
| `tests/Feature/Registration/RegistrationFlowTest.php` | Feature Test |
| `tests/Feature/Models/PaymentInformationTest.php` | Feature Test |

### Modified Files
| File | Changes |
|------|---------|
| `app/Domain/Registration/FormData.php` | Add personalInfo + paymentInfo properties, methods, serialization |
| `app/Http/Controllers/Registration/RegistrationController.php` | Add savePersonalInformationForm, show/savePaymentInformationForm; pass formData to personal info view |
| `app/Models/Member.php` | Add paymentInformation() HasOne relation |
| `resources/views/pages/register/3-personal-information.blade.php` | Complete rewrite with all fields, POST method, formData binding |
| `routes/web.php` | Add POST personal-info, GET+POST payment-info routes |
| `lang/nl/labels.php` | Add payment-related labels |
| `lang/nl/texts.php` | Add payment information explainer texts |
| `lang/nl/validation.php` | Add attribute translations for new fields |
| `app/Filament/Admin/Resources/Members/Schemas/MemberForm.php` | Add Payment Information tab |
