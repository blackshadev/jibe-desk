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
