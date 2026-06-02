<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Members\RelationManagers;

use App\Filament\Admin\Resources\Members\MemberResource;
use App\Models\Member;
use App\Models\Household;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

final class HouseholdMembersRelationManager extends RelationManager
{
    protected static string $relationship = 'householdMembers';

    protected static ?string $recordTitleAttribute = 'name';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('labels.name'))->searchable(['first_name', 'last_name', 'infix_name']),
                TextColumn::make('membership.name')->label(__('labels.membership')),
                TextColumn::make('age')->label(__('labels.age')),
            ])
            ->headerActions([
                // Default action: join an existing household (or create a new one from the modal)
                Action::make('join_or_create_household')
                    ->label(__('labels.join_or_create_household'))
                    ->icon('heroicon-o-user-group')
                    ->visible(static fn (RelationManager $livewire): bool => $livewire->getOwnerRecord()->household_id === null)
                    ->schema([
                        Forms\Components\Select::make('existing_household_id')
                            ->label(__('labels.select_household'))
                            ->options(fn (): array => Household::with('members')->get()->mapWithKeys(fn (Household $h) => [$h->id => $h->member_names])->toArray())
                            ->searchable()
                            ->nullable(),

                        Forms\Components\Toggle::make('create_new')
                            ->label(__('labels.create_new_household'))
                            ->helperText(__('labels.create_new_household_helper'))
                            ->default(false),
                    ])
                    ->action(static function (RelationManager $livewire, array $data): void {
                        if (!empty($data['create_new'])) {
                            $household = Household::create();
                            $livewire->getOwnerRecord()->update(['household_id' => $household->id]);

                            Notification::make()->success()->title(__('notifications.household_created'))->send();
                            return;
                        }

                        $selected = $data['existing_household_id'] ?? null;

                        $livewire->getOwnerRecord()->update(['household_id' => $selected]);

                        Notification::make()->success()->title(__('notifications.member_added_to_household'))->send();
                    }),

                Action::make('add_member')
                    ->label(__('labels.add_member_to_household'))
                    ->icon('heroicon-o-user-plus')
                    ->visible(static fn (RelationManager $livewire): bool => $livewire->getOwnerRecord()->household_id !== null)
                    ->schema([
                        Forms\Components\Select::make('member_id')
                            ->label(__('labels.member'))
                            ->options(function (): array {
                                return Member::whereNull('household_id')
                                    ->get()
                                    ->mapWithKeys(fn (Member $m) => [$m->id => $m->name])
                                    ->toArray();
                            })
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        $member = Member::findOrFail($data['member_id']);
                        $member->update(['household_id' => $this->getOwnerRecord()->household_id]);

                        Notification::make()->success()->title(__('notifications.member_added_to_household'))->send();
                    }),
            ])
            ->recordActions([
                Action::make('remove_from_household')
                    ->label(__('labels.remove_from_household'))
                    ->icon('heroicon-o-trash')
                    ->requiresConfirmation()
                    ->action(function (Member $record): void {
                        $record->update(['household_id' => null]);

                        Notification::make()->success()->title(__('notifications.member_removed_from_household'))->send();
                    }),
            ]);
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('labels.household');
    }

    public static function getModelLabel(): string
    {
        return mb_strtolower(__('labels.household_member'));
    }

    public static function getPluralModelLabel(): string
    {
        return mb_strtolower(__('labels.household_members'));
    }
}
