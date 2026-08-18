<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\InventoryItems;

use App\Filament\Admin\Clusters\Bookkeeping\BookkeepingCluster;
use App\Filament\Admin\Navigation\NavigationGroup;
use App\Filament\Admin\Resources\InventoryItems\Pages\CreateInventoryItem;
use App\Filament\Admin\Resources\InventoryItems\Pages\EditInventoryItem;
use App\Filament\Admin\Resources\InventoryItems\Pages\ListInventoryItems;
use App\Filament\Admin\Resources\InventoryItems\Schemas\InventoryItemForm;
use App\Filament\Admin\Resources\InventoryItems\Tables\InventoryItemsTable;
use App\Models\InventoryItem;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Override;
use UnitEnum;

final class InventoryItemResource extends Resource
{
    #[Override]
    protected static bool $isGloballySearchable = false;

    #[Override]
    protected static ?string $model = InventoryItem::class;

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::ArchiveBox;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Bookkeeping;

    #[Override]
    protected static ?string $cluster = BookkeepingCluster::class;

    #[Override]
    protected static ?int $navigationSort = 5;

    #[Override]
    public static function form(Schema $schema): Schema
    {
        return InventoryItemForm::configure($schema);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return InventoryItemsTable::configure($table);
    }

    #[Override]
    public static function getLabel(): string
    {
        return __('labels.inventory_item');
    }

    #[Override]
    public static function getPluralLabel(): string
    {
        return __('labels.inventory_items');
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ListInventoryItems::route('/'),
            'create' => CreateInventoryItem::route('/create'),
            'edit' => EditInventoryItem::route('/{record}/edit'),
        ];
    }
}
