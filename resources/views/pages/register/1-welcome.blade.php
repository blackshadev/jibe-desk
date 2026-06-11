<x-layout.default title="{{__('titles.register')}}" subtitle="{{__('titles.welcome') }}">
    <x-atoms.container>
        <div class="flex flex-col gap-2 leading-tight text-gray-900">
            <p>
                {!! __('texts.register.welcome.intro') !!}
            </p>

            <p> {!! __('texts.register.welcome.explainer') !!}</p>

            <div class="mt-2">
                {!! __('texts.register.welcome.steps') !!}
            </div>

            <form  method="POST">
                @csrf
                <x-molecule.form-buttons :back="false" />
            </form>
        </div>
    </x-atoms.container>
</x-layout.default>
