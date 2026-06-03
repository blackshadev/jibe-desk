<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Households;

use App\Filament\Admin\Navigation\NavigationGroup;
use App\Filament\Admin\Resources\Households\Pages\CreateHousehold;
use App\Filament\Admin\Resources\Households\Pages\EditHousehold;
use App\Filament\Admin\Resources\Households\Pages\ListHouseholds;
use App\Filament\Admin\Resources\Households\RelationManagers\HouseholdMembersRelationManager;
use App\Filament\Admin\Resources\Households\Table\HouseholdTable;
use App\Models\Household;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

final class HouseholdResource extends Resource
{
    protected static ?string $model = Household::class;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::MemberAdministration;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Home;

    public static function getRelations(): array
    {
        return [
            HouseholdMembersRelationManager::make(),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHouseholds::route('/'),
            'edit' => EditHousehold::route('/{record}/edit'),
            'create' => CreateHousehold::route('/create'),
        ];
    }

    public static function table(Table $table): Table
    {
        return HouseholdTable::configure($table);
    }

    public static function getPluralLabel(): string
    {
        return __('labels.households');
    }

    public static function getLabel(): string
    {
        return __('labels.household');
    }
}
