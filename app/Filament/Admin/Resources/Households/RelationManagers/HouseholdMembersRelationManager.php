<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Households\RelationManagers;

use App\Filament\Admin\Resources\Members\MemberResource;
use App\Models\Member;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

final class HouseholdMembersRelationManager extends RelationManager
{
    protected static string $relationship = 'members';

    protected static ?string $recordTitleAttribute = 'name';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('labels.name')),
                TextColumn::make('membership.name')->label(__('labels.membership')),
                TextColumn::make('age')->label(__('labels.age')),
            ])
            ->headerActions([
                Action::make('add_member')
                    ->label(__('labels.add_member_to_household'))
                    ->icon(Heroicon::Plus)
                    ->schema([
                        Select::make('member_id')
                            ->label(__('labels.member'))
                            ->options(fn () => Member::whereNull('household_id')->get()->mapWithKeys(fn (Member $m) => [$m->id => $m->name])->toArray())
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        $member = Member::findOrFail($data['member_id']);
                        $member->update(['household_id' => $this->getOwnerRecord()->id]);

                        Notification::make()->success()->title(__('notifications.member_added_to_household'))->send();
                    }),
            ])
            ->recordUrl(static fn (Member $record): string => MemberResource::getUrl('edit', ['record' => $record]))
            ->recordActions([
                Action::make('remove')
                    ->label(__('labels.remove_from_household'))
                    ->icon(Heroicon::XMark)
                    ->requiresConfirmation()
                    ->action(fn (Member $record) => $record->update(['household_id' => null])),
            ]);
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('labels.household_members');
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
