<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\StorageSpaceLocations;

use App\Filament\Admin\Navigation\NavigationGroup;
use App\Filament\Admin\Resources\StorageSpaceLocations\Pages\CreateStorageSpaceLocation;
use App\Filament\Admin\Resources\StorageSpaceLocations\Pages\EditStorageSpaceLocation;
use App\Filament\Admin\Resources\StorageSpaceLocations\Pages\ListStorageSpaceLocations;
use App\Filament\Admin\Resources\StorageSpaceLocations\Schemas\StorageSpaceLocationForm;
use App\Filament\Admin\Resources\StorageSpaceLocations\Tables\StorageSpaceLocationsTable;
use App\Models\StorageSpaceLocation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

final class StorageSpaceLocationResource extends Resource
{
    protected static bool $isGloballySearchable = false;

    protected static ?string $model = StorageSpaceLocation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::MapPin;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::MemberAdministration;

    public static function form(Schema $schema): Schema
    {
        return StorageSpaceLocationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StorageSpaceLocationsTable::configure($table);
    }

    public static function getLabel(): string
    {
        return __('labels.storage_space_location');
    }

    public static function getPluralLabel(): string
    {
        return __('labels.storage_space_locations');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStorageSpaceLocations::route('/'),
            'create' => CreateStorageSpaceLocation::route('/create'),
            'edit' => EditStorageSpaceLocation::route('/{record}/edit'),
        ];
    }
}
