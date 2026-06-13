<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Households\RelationManagers;

use App\Filament\Admin\Resources\Households\Actions\HouseholdMemberActions;
use App\Filament\Admin\Resources\Members\MemberResource;
use App\Models\Member;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Override;

final class HouseholdMembersRelationManager extends RelationManager
{
    protected static string $relationship = 'members';

    protected static ?string $recordTitleAttribute = 'name';

    #[Override]
    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('labels.name')),
                TextColumn::make('membership.name')->label(__('labels.membership')),
                TextColumn::make('age')->label(__('labels.age')),
            ])
            ->headerActions(HouseholdMemberActions::headerActionsForHousehold())
            ->recordUrl(static fn (Member $record): string => MemberResource::getUrl('edit', ['record' => $record]))
            ->recordActions(HouseholdMemberActions::recordActions());
    }

    #[Override]
    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('labels.household_members');
    }

    #[Override]
    public static function getModelLabel(): string
    {
        return mb_strtolower(__('labels.household_member'));
    }

    #[Override]
    public static function getPluralModelLabel(): string
    {
        return mb_strtolower(__('labels.household_members'));
    }
}
