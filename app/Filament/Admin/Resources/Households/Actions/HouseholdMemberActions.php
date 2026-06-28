<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Households\Actions;

use App\Models\Household;
use App\Models\Member;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;

final class HouseholdMemberActions
{
    public static function headerActionsForHousehold(): array
    {
        return [
            Action::make('add_member')
                ->label(__('labels.add_member_to_household'))
                ->icon(Heroicon::Plus)
                ->schema([
                    Select::make('member_id')
                        ->label(__('labels.member'))
                        ->options(
                            static fn (): array => Member::query()
                                ->whereNull('household_id')
                                ->get()
                                ->mapWithKeys(static fn (Member $m) => [$m->id => $m->name])
                                ->toArray(),
                        )
                        ->searchable()
                        ->required(),
                ])
                ->action(static function (RelationManager $livewire, array $data): void {
                    /** @var Household $household */
                    $household = $livewire->getOwnerRecord();
                    $member = Member::findOrFail($data['member_id']);
                    $member->update(['household_id' => $household->id]);
                })
                ->visible(static function (RelationManager $livewire): bool {
                    /** @var Household $household */
                    $household = $livewire->getOwnerRecord();

                    return auth()->user()?->can('update', $household) ?? false;
                })
                ->successNotificationTitle(__('notifications.member_added_to_household')),
        ];
    }

    public static function recordActions(): array
    {
        return [
            Action::make('remove_from_household')
                ->label(__('labels.remove_from_household'))
                ->icon(Heroicon::XMark)
                ->requiresConfirmation()
                ->action(static function (Member $record): void {
                    $record->update(['household_id' => null]);
                })
                ->visible(static function (RelationManager $livewire): bool {
                    /** @var Household $household */
                    $household = $livewire->getOwnerRecord();

                    return auth()->user()?->can('update', $household) ?? false;
                })
                ->successNotificationTitle(__('notifications.member_removed_from_household')),
        ];
    }

    public static function headerActionsForMember(): array
    {
        return [
            Action::make('join_or_create_household')
                ->label(__('labels.join_or_create_household'))
                ->icon('heroicon-o-user-group')
                ->visible(static function (RelationManager $livewire): bool {
                    /** @var Member $member */
                    $member = $livewire->getOwnerRecord();

                    $isAllowed = auth()->user()?->can('create', Household::class) ?? false;
                    return $member->household_id === null && $isAllowed;
                })
                ->schema([
                    Toggle::make('create_new')
                        ->label(__('labels.create_new_household'))
                        ->helperText(__('labels.create_new_household_helper'))
                        ->default(false)
                        ->live(),

                    Select::make('existing_household_id')
                        ->label(__('labels.select_household'))
                        ->visible(static fn (Get $get): bool => !$get('create_new'))
                        ->options(
                            static fn (): array => Household::query()
                                ->with('members')
                                ->get()
                                ->mapWithKeys(static fn (Household $h) => [$h->id => $h->member_names])
                                ->toArray(),
                        )
                        ->searchable()
                        ->nullable(),
                ])
                ->action(static function (RelationManager $livewire, array $data): void {
                    if ($data['create_new'] === true) {
                        $household = Household::create();
                        $livewire->getOwnerRecord()->update(['household_id' => $household->id]);

                        return;
                    }

                    $selected = $data['existing_household_id'] ?? null;
                    if (!$selected) {
                        return;
                    }

                    $livewire->getOwnerRecord()->update(['household_id' => $selected]);
                })
                ->successNotificationTitle(static function (array $data): string {
                    if ($data['create_new'] === true) {
                        return __('notifications.household_created');
                    }

                    return __('notifications.member_added_to_household');
                }),

            Action::make('add_member')
                ->label(__('labels.add_member_to_household'))
                ->icon('heroicon-o-user-plus')
                ->visible(static function (RelationManager $livewire): bool {
                    /** @var Member $member */
                    $member = $livewire->getOwnerRecord();

                    $isAllowed = auth()->user()?->can('create', Household::class) ?? false;
                    return $member->household_id !== null && $isAllowed;
                })
                ->schema([
                    Select::make('member_id')
                        ->label(__('labels.member'))
                        ->options(
                            static fn (): array => Member::query()
                                ->whereNull('household_id')
                                ->get()
                                ->mapWithKeys(static fn (Member $m) => [$m->id => $m->name])
                                ->toArray(),
                        )
                        ->searchable()
                        ->required(),
                ])
                ->action(static function (RelationManager $livewire, array $data): void {
                    /** @var Member $member */
                    $member = $livewire->getOwnerRecord();
                    $targetMember = Member::findOrFail($data['member_id']);

                    $targetMember->update(['household_id' => $member->household_id]);
                })
                ->successNotificationTitle(__('notifications.member_added_to_household')),
        ];
    }
}
