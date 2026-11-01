@php /** @var list<\App\Models\CostCenterGroup> $groups */ @endphp

<div class="flex flex-wrap items-center gap-2 text-sm">
    @foreach ($groups as $group)
        @if (!$loop->first)
            <x-filament::icon
                icon="heroicon-m-chevron-right"
                class="h-4 w-4 text-gray-400 dark:text-gray-500"
            />
        @endif

        <x-filament::link
            :href="\App\Filament\Admin\Resources\CostCenterGroups\CostCenterGroupResource::getUrl('edit', ['record' => $group])"
            weight="medium"
        >
            {{ $group->label }}
        </x-filament::link>
    @endforeach
</div>
