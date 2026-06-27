<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PurchaseOrders;

use App\Filament\Admin\Navigation\NavigationGroup;
use App\Filament\Admin\Resources\PurchaseOrders\Pages\CreatePurchaseOrder;
use App\Filament\Admin\Resources\PurchaseOrders\Pages\EditPurchaseOrder;
use App\Filament\Admin\Resources\PurchaseOrders\Pages\ListPurchaseOrders;
use App\Filament\Admin\Resources\PurchaseOrders\Pages\ViewPurchaseOrder;
use App\Filament\Admin\Resources\PurchaseOrders\Schemas\PurchaseOrderForm;
use App\Filament\Admin\Resources\PurchaseOrders\Tables\PurchaseOrdersTable;
use App\Models\PurchaseOrder;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Override;
use UnitEnum;

final class PurchaseOrderResource extends Resource
{
    protected static ?string $model = PurchaseOrder::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ShoppingCart;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Bookkeeping;

    protected static ?string $recordTitleAttribute = 'description';

    #[Override]
    public static function form(Schema $schema): Schema
    {
        return PurchaseOrderForm::configure($schema);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return PurchaseOrdersTable::configure($table);
    }

    #[Override]
    public static function getPluralLabel(): string
    {
        return __('labels.purchase_orders');
    }

    #[Override]
    public static function getLabel(): string
    {
        return __('labels.purchase_order');
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ListPurchaseOrders::route('/'),
            'create' => CreatePurchaseOrder::route('/create'),
            'edit' => EditPurchaseOrder::route('/{record}/edit'),
            'view' => ViewPurchaseOrder::route('/{record}'),
        ];
    }
}
