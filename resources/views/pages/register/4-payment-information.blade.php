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
                :value="$formData->paymentInfo->mandateAcceptedDate !== null"
                label="{{ __('labels.mandate_accepted') }}"
                description="{{ __('texts.register.payment_information.mandate_description') }}"
            />

            <x-molecule.form-buttons/>
        </form>
    </x-atoms.container>
</x-layout.default>
