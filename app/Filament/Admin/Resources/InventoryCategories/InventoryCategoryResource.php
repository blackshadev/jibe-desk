<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\InventoryCategories;

use App\Filament\Admin\Navigation\NavigationGroup;
use App\Filament\Admin\Resources\InventoryCategories\Pages\CreateInventoryCategory;
use App\Filament\Admin\Resources\InventoryCategories\Pages\EditInventoryCategory;
use App\Filament\Admin\Resources\InventoryCategories\Pages\ListInventoryCategories;
use App\Filament\Admin\Resources\InventoryCategories\Schemas\InventoryCategoryForm;
use App\Filament\Admin\Resources\InventoryCategories\Tables\InventoryCategoriesTable;
use App\Models\InventoryCategory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Override;
use UnitEnum;

final class InventoryCategoryResource extends Resource
{
    #[Override]
    protected static bool $isGloballySearchable = false;

    #[Override]
    protected static ?string $model = InventoryCategory::class;

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::Tag;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Bookkeeping;

    #[Override]
    protected static bool $shouldRegisterNavigation = false;

    #[Override]
    protected static ?string $recordTitleAttribute = 'name';

    #[Override]
    public static function form(Schema $schema): Schema
    {
        return InventoryCategoryForm::configure($schema);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return InventoryCategoriesTable::configure($table);
    }

    #[Override]
    public static function getLabel(): string
    {
        return __('labels.inventory_category');
    }

    #[Override]
    public static function getPluralLabel(): string
    {
        return __('labels.inventory_categories');
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ListInventoryCategories::route('/'),
            'create' => CreateInventoryCategory::route('/create'),
            'edit' => EditInventoryCategory::route('/{record}/edit'),
        ];
    }
}
