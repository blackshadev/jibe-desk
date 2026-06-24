<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\CostCenters;

use App\Filament\Admin\Navigation\NavigationGroup;
use App\Filament\Admin\Resources\CostCenters\Pages\CreateCostCenter;
use App\Filament\Admin\Resources\CostCenters\Pages\EditCostCenter;
use App\Filament\Admin\Resources\CostCenters\Pages\ListCostCenters;
use App\Filament\Admin\Resources\CostCenters\Schemas\CostCenterForm;
use App\Filament\Admin\Resources\CostCenters\Tables\CostCentersTable;
use App\Models\CostCenter;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Override;
use UnitEnum;

final class CostCenterResource extends Resource
{
    protected static bool $isGloballySearchable = false;

    protected static ?string $model = CostCenter::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Banknotes;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Bookkeeping;

    protected static ?string $recordTitleAttribute = 'title';

    #[Override]
    public static function form(Schema $schema): Schema
    {
        return CostCenterForm::configure($schema);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return CostCentersTable::configure($table);
    }

    #[Override]
    public static function getLabel(): string
    {
        return __('labels.cost_center');
    }

    #[Override]
    public static function getPluralLabel(): string
    {
        return __('labels.cost_centers');
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ListCostCenters::route('/'),
            'create' => CreateCostCenter::route('/create'),
            'edit' => EditCostCenter::route('/{record}/edit'),
        ];
    }
}
