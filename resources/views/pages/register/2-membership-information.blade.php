@php use App\Domain\Registration\FormData; @endphp
@php /** @var FormData $formData */ @endphp
<x-layout.default title="{{__('titles.register')}}" subtitle="{{__('titles.membership_information') }}">
    <x-atoms.container>
        <form class="flex flex-col gap-4" method="POST">
            @csrf
            <p class="text-gray-700 text-sm/relaxed">
                {{ __('texts.register.membership_information.explainer') }}
            </p>

            <x-atoms.divider>{{ __('labels.registration.membership_information.activities') }}</x-atoms.divider>

            @error('membership_activities')
            <x-atoms.error>
                {{ $message }}
            </x-atoms.error>
            @enderror

            <x-molecule.checkbox-row
                name="windsurfing_lessons"
                :value="$formData->membership->regularWindsurfingLessons"
                label="{{ __('labels.registration.membership_information.windsurfing_lessons') }}"
                description="{{ __('texts.register.membership_information.windsurfing_lessons_description') }}"
            />

            <x-molecule.checkbox-row
                name="rtc_lessons"
                :value="$formData->membership->rtc"
                label="{{ __('labels.registration.membership_information.rtc_lessons') }}"
                description="{{ __('texts.register.membership_information.rtc_lessons_description') }}"
            />

            <x-molecule.checkbox-row
                name="club_access"
                :value="$formData->membership->clubhouseAccess"
                label="{{ __('labels.registration.membership_information.club_access') }}"
                description="{{ __('texts.register.membership_information.club_access_description') }}"
            />

            <x-molecule.checkbox-row
                name="storage"
                :value="$formData->membership->boardStorage"
                label="{{ __('labels.registration.membership_information.storage') }}"
                description="{{ __('texts.register.membership_information.storage_description') }}"
            />

            <x-atoms.divider>{{ __('labels.registration.membership_information.watersport_federation') }}</x-atoms.divider>

            <x-molecule.form-row
                label="{{ __('labels.registration.membership_information.watersport_federation_number') }}"
                :value="$formData->membership->watersportFederationNumber"
                description="Heb je reeds een watersport verbond nummer, dan kun je deze hier invullen. Zo niet dan mag deze leeg blijven."
                name="watersport_federation_number">
                <x-atoms.inputs.text name="watersport_federation_number" placeholder="12345"/>
            </x-molecule.form-row>

            <x-molecule.form-buttons/>
        </form>
    </x-atoms.container>
</x-layout.default>
