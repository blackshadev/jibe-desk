<x-filament-widgets::widget>
    <x-filament::section :heading="__('labels.membership_card')">
        <dl class="grid gap-3 text-sm">
            <div class="flex justify-between gap-4">
                <dt class="text-gray-500 dark:text-gray-400">{{ __('labels.member') }}</dt>
                <dd class="font-medium text-gray-950 dark:text-white">{{ $memberName }}</dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="text-gray-500 dark:text-gray-400">{{ __('labels.membership') }}</dt>
                <dd class="font-medium text-gray-950 dark:text-white">{{ $membershipName ?? '—' }}</dd>
            </div>
            @if ($householdName !== null)
                <div class="flex justify-between gap-4">
                    <dt class="text-gray-500 dark:text-gray-400">{{ __('labels.household') }}</dt>
                    <dd class="font-medium text-gray-950 dark:text-white">
                        {{ $householdName }}
                        <span class="text-gray-500 dark:text-gray-400">({{ trans_choice('labels.household_member_count', $householdSize, ['count' => $householdSize]) }})</span>
                    </dd>
                </div>
            @endif
            <div class="flex justify-between gap-4">
                <dt class="text-gray-500 dark:text-gray-400">{{ __('labels.volunteer') }}</dt>
                <dd class="font-medium text-gray-950 dark:text-white">
                    {{ $isVolunteer ? __('labels.yes') : __('labels.no') }}
                </dd>
            </div>
            <div class="flex justify-between gap-4 relative">
                <dt class="text-gray-500 dark:text-gray-400">{{ __('labels.roles') }}</dt>
                <dd class="font-medium text-gray-950 dark:text-white text-right max-w-3/4">
                    {{ $roles === [] ? '—' : implode(', ', array_map(static fn (string $role) => __("labels.role_names.{$role}"), $roles)) }}
                </dd>
            </div>
        </dl>
    </x-filament::section>
</x-filament-widgets::widget>
