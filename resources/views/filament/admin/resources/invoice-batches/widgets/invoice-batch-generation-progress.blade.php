@php
    $pollingInterval = $this->getPollingInterval();
@endphp

<x-filament-widgets::widget
    :attributes="
        (new \Illuminate\View\ComponentAttributeBag)
            ->merge([
                'wire:poll.' . $pollingInterval => $pollingInterval ? true : null,
            ], escape: false)
            ->class(['fi-wi-invoice-batch-generation-progress'])
    "
>
    <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <h3 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
            {{ __('labels.generation_in_progress') }}
        </h3>

        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
            {{ __('labels.invoices_added', ['added' => $addedCount, 'total' => $totalCount]) }}
            <span aria-hidden="true">&middot;</span>
            {{ __('labels.duration') }}: {{ $duration }}
        </p>

        <div
            class="mt-4 h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700"
            role="progressbar"
            aria-valuenow="{{ $progressPercent }}"
            aria-valuemin="0"
            aria-valuemax="100"
        >
            <div class="h-full rounded-full bg-primary-600 transition-all" style="width: {{ $progressPercent }}%"></div>
        </div>
    </div>
</x-filament-widgets::widget>
