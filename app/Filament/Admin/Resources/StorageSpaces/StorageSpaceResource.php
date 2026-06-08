<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\StorageSpaces;

use App\Filament\Admin\Navigation\NavigationGroup;
use App\Filament\Admin\Resources\StorageSpaces\Pages\CreateStorageSpace;
use App\Filament\Admin\Resources\StorageSpaces\Pages\EditStorageSpace;
use App\Filament\Admin\Resources\StorageSpaces\Pages\ListStorageSpaces;
use App\Filament\Admin\Resources\StorageSpaces\RelationManagers\StorageSpaceRentalsRelationManager;
use App\Filament\Admin\Resources\StorageSpaces\Schemas\StorageSpaceForm;
use App\Filament\Admin\Resources\StorageSpaces\Tables\StorageSpacesTable;
use App\Models\StorageSpace;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

final class StorageSpaceResource extends Resource
{
    protected static bool $isGloballySearchable = false;

    protected static ?string $model = StorageSpace::class;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Rental;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Squares2x2;

    public static function form(Schema $schema): Schema
    {
        return StorageSpaceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StorageSpacesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            StorageSpaceRentalsRelationManager::make(),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStorageSpaces::route('/'),
            'create' => CreateStorageSpace::route('/create'),
            'edit' => EditStorageSpace::route('/{record}/edit'),
        ];
    }

    public static function getLabel(): string
    {
        return __('labels.storage_space');
    }

    public static function getPluralLabel(): string
    {
        return __('labels.storage_spaces');
    }
}
