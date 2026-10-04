<x-layout.default title="{{ __('titles.reset_password') }}">
    <x-atoms.container>
        <form class="flex flex-col gap-4" method="POST" action="{{ route('password.update') }}">
            @csrf

            <input type="hidden" name="token" value="{{ $request->route('token') }}">

            @if (session('status'))
                <p class="text-sm font-medium text-green-600">{{ session('status') }}</p>
            @endif

            <p class="text-gray-700 text-sm/relaxed">
                {{ __('texts.reset_password.explainer') }}
            </p>

            <x-molecule.form-row label="{{ __('labels.email') }}" name="email">
                <x-atoms.inputs.text name="email" :value="$request->email" placeholder="jan@voorbeeld.nl" />
            </x-molecule.form-row>

            <x-molecule.form-row label="{{ __('labels.password') }}" name="password">
                <x-atoms.inputs.password name="password" />
            </x-molecule.form-row>

            <x-molecule.form-row label="{{ __('labels.password_confirmation') }}" name="password_confirmation">
                <x-atoms.inputs.password name="password_confirmation" />
            </x-molecule.form-row>

            <x-molecule.form-buttons :back="false" nextLabel="{{ __('labels.set_password') }}" />
        </form>
    </x-atoms.container>
</x-layout.default>
