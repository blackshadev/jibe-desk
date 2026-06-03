<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Members\RelationManagers;

use App\Filament\Admin\Resources\Households\Actions\HouseholdMemberActions;
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
            ->headerActions(HouseholdMemberActions::headerActionsForMember())
            ->recordActions(HouseholdMemberActions::recordActions());
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
