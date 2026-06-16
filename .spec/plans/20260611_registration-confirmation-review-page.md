# Registration Confirmation & Review Page Implementation Plan

## Overview

Add a review/overview page as the final step before registration completion, where users can review all entered data, edit individual sections, and confirm their registration with a checkbox acceptance.

## Current Flow
```
Welcome → Membership → Personal Info → Payment Info → Confirmation (simple success page)
```

## Target Flow
```
Welcome → Membership → Personal Info → Payment Info → Review/Overview → Success
```

## Implementation Steps

### 1. Add `clear()` Method to FormDataRepository

**Files to modify:**
- `app/Domain/Registration/FormDataRepository.php`
- `app/Infrastructure/Registration/FormDataSessionRepository.php`

**Changes:**
```php
// FormDataRepository.php - Add to interface
public function clear(): void;

// FormDataSessionRepository.php - Implement
public function clear(): void
{
    Session::forget(self::SESSION_KEY);
}
```

### 2. Create ConfirmRegistrationRequest Form Request

**File:** `app/Http/Requests/Registration/ConfirmRegistrationRequest.php`

**Validation rules:**
```php
public function rules(): array
{
    return [
        'confirm_data_correct' => ['accepted'],
        'confirm_membership' => ['accepted'],
    ];
}
```

**Custom messages (Dutch):**
```php
public function messages(): array
{
    return [
        'confirm_data_correct.accepted' => 'Bevestig dat alle gegevens correct zijn ingevuld.',
        'confirm_membership.accepted' => 'Bevestig dat je lid wilt worden van de vereniging.',
    ];
}
```

### 3. Add Controller Methods

**File:** `app/Http/Controllers/Registration/RegistrationController.php`

Add two methods:

```php
public function showConfirmationForm(): View | RedirectResponse
{
    $formData = $this->formDataRepository->get();
    if ($formData->isStepDisallowed(Step::Confirmation)) {
        return redirect()->route('register.welcome');
    }

    return view('pages.register.5-review', compact('formData'));
}

public function confirmRegistration(ConfirmRegistrationRequest $request): RedirectResponse
{
    $formData = $this->formDataRepository->get();
    
    // TODO: Save registration data to models (Member, Membership, PaymentInfo, etc.)
    
    $this->formDataRepository->clear();
    
    return redirect()->route('register.success');
}
```

### 4. Update Routes

**File:** `routes/web.php`

Replace the closure-based confirmation route with controller methods:

```php
// Review/Overview page (new step 5)
Route::get('/registratie/bevestiging', [Controllers\Registration\RegistrationController::class, 'showConfirmationForm'])->name('register.confirmation');
Route::post('/registratie/bevestiging', [Controllers\Registration\RegistrationController::class, 'confirmRegistration']);

// Success page (new step 6)
Route::get('/registratie/succes', static fn () => view('pages.register.6-success'))->name('register.success');
```

### 5. Create Review Blade View

**File:** `resources/views/pages/register/5-confirmation.blade.php`

**Structure:**
```blade
@php use App\Domain\Registration\FormData; @endphp
@php /** @var FormData $formData */ @endphp
<x-layout.default title="{{__('titles.register')}}" subtitle="{{__('titles.confirmation') }}">
    <x-atoms.container>
        <form class="flex flex-col gap-6" method="POST">
            @csrf
            
            <p class="text-gray-700 text-sm/relaxed">
                {{ __('texts.register.confirmation.explainer') }}
            </p>

            {{-- Membership Information Section --}}
            <section class="border border-gray-200 rounded-lg p-4">
                <div class="flex justify-between items-center mb-3">
                    <h3 class="font-semibold text-gray-900">{{ __('labels.registration.membership_information.activities') }}</h3>
                    <a href="{{ route('register.membership') }}" class="text-primary-600 hover:text-primary-700 text-sm font-medium">
                        {{ __('labels.edit') }}
                    </a>
                </div>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-600">{{ __('labels.registration.membership_information.windsurfing_lessons') }}</dt>
                        <dd class="font-medium">{{ $formData->membership->regularWindsurfingLessons ? __('labels.yes') : __('labels.no') }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-600">{{ __('labels.registration.membership_information.rtc_lessons') }}</dt>
                        <dd class="font-medium">{{ $formData->membership->rtc ? __('labels.yes') : __('labels.no') }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-600">{{ __('labels.registration.membership_information.club_access') }}</dt>
                        <dd class="font-medium">{{ $formData->membership->clubhouseAccess ? __('labels.yes') : __('labels.no') }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-600">{{ __('labels.registration.membership_information.storage') }}</dt>
                        <dd class="font-medium">{{ $formData->membership->boardStorage ? __('labels.yes') : __('labels.no') }}</dd>
                    </div>
                    @if($formData->membership->watersportFederationNumber)
                    <div class="flex justify-between">
                        <dt class="text-gray-600">{{ __('labels.registration.membership_information.watersport_federation_number') }}</dt>
                        <dd class="font-medium">{{ $formData->membership->watersportFederationNumber }}</dd>
                    </div>
                    @endif
                </dl>
            </section>

            {{-- Personal Information Section --}}
            <section class="border border-gray-200 rounded-lg p-4">
                <div class="flex justify-between items-center mb-3">
                    <h3 class="font-semibold text-gray-900">{{ __('labels.personal_information') }}</h3>
                    <a href="{{ route('register.personal-information') }}" class="text-primary-600 hover:text-primary-700 text-sm font-medium">
                        {{ __('labels.edit') }}
                    </a>
                </div>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-600">{{ __('labels.name') }}</dt>
                        <dd class="font-medium">
                            {{ $formData->personalInfo->firstName }}
                            @if($formData->personalInfo->infixName) {{ $formData->personalInfo->infixName }}@endif
                            {{ $formData->personalInfo->lastName }}
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-600">{{ __('labels.email') }}</dt>
                        <dd class="font-medium">{{ $formData->personalInfo->email }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-600">{{ __('labels.gender') }}</dt>
                        <dd class="font-medium">{{ __('labels.genders')[$formData->personalInfo->gender] ?? $formData->personalInfo->gender }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-600">{{ __('labels.birthdate') }}</dt>
                        <dd class="font-medium">{{ $formData->personalInfo->birthdate }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-600">{{ __('labels.address_information') }}</dt>
                        <dd class="font-medium text-right">
                            {{ $formData->personalInfo->addressStreet }} {{ $formData->personalInfo->addressHousenumber }}
                            @if($formData->personalInfo->addressHousenumberAddition){{ $formData->personalInfo->addressHousenumberAddition }}@endif
                            <br>
                            {{ $formData->personalInfo->addressPostalcode }} {{ $formData->personalInfo->addressCity }}
                        </dd>
                    </div>
                </dl>
            </section>

            {{-- Payment Information Section --}}
            <section class="border border-gray-200 rounded-lg p-4">
                <div class="flex justify-between items-center mb-3">
                    <h3 class="font-semibold text-gray-900">{{ __('labels.payment_information') }}</h3>
                    <a href="{{ route('register.payment-information') }}" class="text-primary-600 hover:text-primary-700 text-sm font-medium">
                        {{ __('labels.edit') }}
                    </a>
                </div>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-600">{{ __('labels.banking_account_number') }}</dt>
                        <dd class="font-medium">{{ $formData->paymentInfo->bankingAccountNumber }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-600">{{ __('labels.banking_bic') }}</dt>
                        <dd class="font-medium">{{ $formData->paymentInfo->bankingBic }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-600">{{ __('labels.banking_account_holder_name') }}</dt>
                        <dd class="font-medium">{{ $formData->paymentInfo->bankingAccountHolderName }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-600">{{ __('labels.mandate_accepted') }}</dt>
                        <dd class="font-medium">{{ $formData->paymentInfo->mandateAcceptedDate ? __('labels.yes') : __('labels.no') }}</dd>
                    </div>
                </dl>
            </section>

            {{-- Confirmation Checkboxes --}}
            <div class="border-t border-gray-200 pt-4 space-y-4">
                <x-molecule.checkbox-row
                    name="confirm_data_correct"
                    label="{{ __('labels.confirm_data_correct') }}"
                    description="{{ __('texts.register.confirmation.confirm_data_correct_description') }}"
                />

                <x-molecule.checkbox-row
                    name="confirm_membership"
                    label="{{ __('labels.confirm_membership') }}"
                    description="{{ __('texts.register.confirmation.confirm_membership_description') }}"
                />
            </div>

            {{-- Submit Button --}}
            <div class="flex justify-between">
                <x-atoms.button class="self-start" type="back">{{__('labels.back')}}</x-atoms.button>
                <x-atoms.button class="self-end" type="submit">{{__('labels.confirm_registration')}}</x-atoms.button>
            </div>
        </form>
    </x-atoms.container>
</x-layout.default>
```

### 6. Create Success Blade View

**File:** `resources/views/pages/register/6-success.blade.php`

```blade
<x-layout.default title="{{__('titles.register')}}" subtitle="{{__('titles.success') }}">
    <x-atoms.container>
        <div class="text-center py-8">
            <svg class="mx-auto h-12 w-12 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <h2 class="mt-4 text-xl font-semibold text-gray-900">{{ __('texts.register.success.title') }}</h2>
            <p class="mt-2 text-gray-700 text-sm/relaxed">
                {{ __('texts.register.success.message') }}
            </p>
        </div>
    </x-atoms.container>
</x-layout.default>
```

### 7. Add Translation Keys

**File:** `lang/nl/titles.php`
```php
'confirmation' => 'Bevestiging',
'success' => 'Gelukt!',
```

**File:** `lang/nl/labels.php`
```php
'edit' => 'Wijzigen',
'yes' => 'Ja',
'no' => 'Nee',
'confirm_data_correct' => 'Ik bevestig dat alle bovenstaande gegevens correct zijn ingevuld',
'confirm_membership' => 'Ik wil lid worden van Watersportvereniging Almere Centraal',
'confirm_registration' => 'Bevestig inschrijving',
```

**File:** `lang/nl/texts.php`
```php
'register' => [
    // ... existing keys ...
    'confirmation' => [
        'explainer' => 'Controleer onderstaande gegevens. Klopt er iets niet? Klik dan op "Wijzigen" om terug te gaan naar het betreffende onderdeel.',
        'confirm_data_correct_description' => 'Door dit aan te vinken bevestig je dat alle gegevens naar waarheid zijn ingevuld.',
        'confirm_membership_description' => 'Door dit aan te vinken ga je akkoord met het lidmaatschap van de vereniging en de bijbehorende voorwaarden.',
    ],
    'success' => [
        'title' => 'Je inschrijving is ontvangen!',
        'message' => 'Bedankt voor je inschrijving. We nemen zo snel mogelijk contact met je op.',
    ],
],
```

### 8. Update Existing Tests

**File:** `tests/Feature/Registration/RegistrationFlowTest.php`

Update the `test_full_registration_flow_end_to_end` test and add new tests:

```php
public function test_confirmation_page_renders_after_payment_info(): void
{
    $this->post(route('register.welcome'));
    $this->post(route('register.membership'), self::VALID_ACTIVITIES);
    $this->post(route('register.personal-information'), self::VALID_PERSONAL_INFO);
    $this->post(route('register.payment-information'), self::VALID_PAYMENT_INFO);

    $response = $this->get(route('register.confirmation'));

    $response->assertOk();
    $response->assertSee('Jan');
    $response->assertSee('Vries');
}

public function test_confirmation_requires_both_checkboxes(): void
{
    $this->post(route('register.welcome'));
    $this->post(route('register.membership'), self::VALID_ACTIVITIES);
    $this->post(route('register.personal-information'), self::VALID_PERSONAL_INFO);
    $this->post(route('register.payment-information'), self::VALID_PAYMENT_INFO);

    $response = $this->post(route('register.confirmation'), []);

    $response->assertSessionHasErrors(['confirm_data_correct', 'confirm_membership']);
}

public function test_confirmation_clears_session_and_redirects_to_success(): void
{
    $this->post(route('register.welcome'));
    $this->post(route('register.membership'), self::VALID_ACTIVITIES);
    $this->post(route('register.personal-information'), self::VALID_PERSONAL_INFO);
    $this->post(route('register.payment-information'), self::VALID_PAYMENT_INFO);

    $response = $this->post(route('register.confirmation'), [
        'confirm_data_correct' => '1',
        'confirm_membership' => '1',
    ]);

    $response->assertRedirect(route('register.success'));
    
    // Verify session is cleared
    $this->get(route('register.membership'))
        ->assertRedirect(route('register.welcome'));
}

public function test_success_page_renders(): void
{
    $response = $this->get(route('register.success'));

    $response->assertOk();
}
```

### 9. Update FormData Step Enum (Optional)

**File:** `app/Domain/Registration/Step.php`

Consider adding a `Success` case if you want to track this state:

```php
enum Step: int
{
    case Initial = -1;
    case Welcome = 0;
    case Membership = 1;
    case PersonalInfo = 2;
    case PaymentInfo = 3;
    case Confirmation = 4;
}
```

## File Summary

### Files to Create
1. `app/Http/Requests/Registration/ConfirmRegistrationRequest.php`
2. `resources/views/pages/register/5-confirmation.blade.php`
3. `resources/views/pages/register/6-success.blade.php`

### Files to Modify
1. `app/Domain/Registration/FormDataRepository.php` - Add `clear()` method
2. `app/Infrastructure/Registration/FormDataSessionRepository.php` - Implement `clear()`
3. `app/Http/Controllers/Registration/RegistrationController.php` - Add `showConfirmationForm()` and `confirmRegistration()` methods
4. `routes/web.php` - Update confirmation route, add success route
5. `lang/nl/titles.php` - Add `confirmation` and `success` keys
6. `lang/nl/labels.php` - Add `edit`, `yes`, `no`, `confirm_data_correct`, `confirm_membership`, `confirm_registration` keys
7. `lang/nl/texts.php` - Add `confirmation` and `success` text blocks
8. `tests/Feature/Registration/RegistrationFlowTest.php` - Update and add tests

## Testing Strategy

1. **Unit Tests**: Test the `clear()` method in FormDataRepository
2. **Feature Tests**: 
   - Confirmation page renders with all data sections
   - Edit buttons link to correct routes
   - Confirmation requires both checkboxes
   - Successful confirmation clears session and redirects
   - Success page renders
   - Full end-to-end flow works

## Notes

- The edit buttons use standard `<a>` tags with `route()` helper to navigate back to specific steps
- The `isStepDisallowed()` logic allows users to go back to previous steps (it only prevents skipping ahead)
- Session clearing happens after successful confirmation
- The TODO comment for saving to models is placed in the `confirmRegistration()` method
- All user-facing strings use Laravel translation helpers for consistency
