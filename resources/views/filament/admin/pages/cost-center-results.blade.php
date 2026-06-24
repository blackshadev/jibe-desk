<x-filament-panels::page>
    <div class="space-y-6">
        <form wire:submit="refreshTable">
            {{ $this->form }}
        </form>

        {{ $this->table }}
    </div>
</x-filament-panels::page>
