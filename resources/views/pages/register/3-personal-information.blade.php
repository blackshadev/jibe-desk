@php use App\Domain\Members\Gender; @endphp
@php use App\Domain\Registration\FormData; @endphp
@php /** @var FormData $formData */ @endphp
<x-layout.default title="{{__('titles.register')}}" subtitle="{{__('titles.personal_information') }}">
    <x-atoms.container>
        <form class="flex flex-col gap-4" method="POST">
            @csrf

            <x-atoms.divider>{{ __('labels.personal_details') }}</x-atoms.divider>

            <div class="flex flex-row gap-2 flex-1 flex-wrap">
                <x-molecule.form-row label="{{__('labels.first_name')}}" name="first_name">
                    <x-atoms.inputs.text name="first_name" :value="$formData->personalInfo->firstName" placeholder="Jan" />
                </x-molecule.form-row>

                <x-molecule.form-row label="{{__('labels.infix_name')}}" name="infix_name">
                    <x-atoms.inputs.text name="infix_name" :value="$formData->personalInfo->infixName" placeholder="de" />
                </x-molecule.form-row>

                <x-molecule.form-row label="{{__('labels.last_name')}}" name="last_name">
                    <x-atoms.inputs.text name="last_name" :value="$formData->personalInfo->lastName" placeholder="Vries" />
                </x-molecule.form-row>
            </div>

            <x-molecule.form-row label="{{__('labels.email')}}" name="email">
                <x-atoms.inputs.text name="email" :value="$formData->personalInfo->email" placeholder="jan@voorbeeld.nl" />
            </x-molecule.form-row>

            <x-molecule.form-row label="{{__('labels.gender')}}" name="gender">
                <x-atoms.inputs.select name="gender" :value="$formData->personalInfo->gender" :options="__('labels.genders')" />
            </x-molecule.form-row>

            <x-molecule.form-row label="{{__('labels.birthdate')}}" name="birthdate">
                <x-atoms.inputs.date name="birthdate" :value="$formData->personalInfo->birthdate" />
            </x-molecule.form-row>

            <x-atoms.divider>{{ __('labels.address_information') }}</x-atoms.divider>

            <x-molecule.form-row label="{{__('labels.address_street')}}" name="address_street">
                <x-atoms.inputs.text name="address_street" :value="$formData->personalInfo->addressStreet" placeholder="Surfstrand" />
            </x-molecule.form-row>

            <div class="flex flex-row gap-2 flex-1 flex-wrap">
                <x-molecule.form-row label="{{__('labels.address_housenumber')}}" name="address_housenumber">
                    <x-atoms.inputs.text name="address_housenumber" :value="$formData->personalInfo->addressHousenumber" placeholder="2" />
                </x-molecule.form-row>

                <x-molecule.form-row label="{{__('labels.address_housenumber_addition')}}" name="address_housenumber_addition">
                    <x-atoms.inputs.text name="address_housenumber_addition" :value="$formData->personalInfo->addressHousenumberAddition" />
                </x-molecule.form-row>
            </div>

            <div class="flex flex-row gap-2 flex-1 flex-wrap">
                <x-molecule.form-row label="{{__('labels.address_postalcode')}}" name="address_postalcode">
                    <x-atoms.inputs.text name="address_postalcode" :value="$formData->personalInfo->addressPostalcode" placeholder="1324CT" />
                </x-molecule.form-row>

                <x-molecule.form-row label="{{__('labels.address_city')}}" name="address_city">
                    <x-atoms.inputs.text name="address_city" :value="$formData->personalInfo->addressCity" placeholder="Almere" />
                </x-molecule.form-row>
            </div>

            <x-molecule.form-buttons/>
        </form>
    </x-atoms.container>
</x-layout.default>
