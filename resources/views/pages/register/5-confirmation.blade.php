@php use App\Domain\Registration\FormData; @endphp
@php /** @var FormData $formData */ @endphp
<x-layout.default title="{{__('titles.register')}}" subtitle="{{__('titles.confirmation') }}">
    <x-atoms.container>
        <form class="flex flex-col gap-6" method="POST">
            @csrf

            <p class="text-gray-700 text-sm/relaxed">
                {{ __('texts.register.confirmation.explainer') }}
            </p>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
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
                            <dd class="font-medium">{{ __('labels.genders.' . $formData->personalInfo->gender->value) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-600">{{ __('labels.birthdate') }}</dt>
                            <dd class="font-medium">{{ $formData->personalInfo->birthdate->format('d-m-Y') }}</dd>
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
            </div>

            {{-- Confirmation Checkboxes --}}
            <div class="flex flex-col gap-4">
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

            <x-molecule.form-buttons
                back="{{route('register.payment-information')}}"
                nextLabel="{{ __('labels.confirm_registration') }}"
            />
        </form>
    </x-atoms.container>
</x-layout.default>
