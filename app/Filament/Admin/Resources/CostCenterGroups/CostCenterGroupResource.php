<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\CostCenterGroups;

use App\Filament\Admin\Clusters\Bookkeeping\BookkeepingCluster;
use App\Filament\Admin\Navigation\NavigationGroup;
use App\Filament\Admin\Resources\CostCenterGroups\Pages\CreateCostCenterGroup;
use App\Filament\Admin\Resources\CostCenterGroups\Pages\EditCostCenterGroup;
use App\Filament\Admin\Resources\CostCenterGroups\Pages\ListCostCenterGroups;
use App\Filament\Admin\Resources\CostCenterGroups\Schemas\CostCenterGroupForm;
use App\Filament\Admin\Resources\CostCenterGroups\Tables\CostCenterGroupsTable;
use App\Models\CostCenterGroup;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Override;
use UnitEnum;

final class CostCenterGroupResource extends Resource
{
    #[Override]
    protected static bool $isGloballySearchable = false;

    #[Override]
    protected static ?string $model = CostCenterGroup::class;

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::Folder;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Bookkeeping;

    #[Override]
    protected static ?string $cluster = BookkeepingCluster::class;

    #[Override]
    protected static ?int $navigationSort = 5;

    #[Override]
    protected static ?string $recordTitleAttribute = 'name';

    #[Override]
    public static function form(Schema $schema): Schema
    {
        return CostCenterGroupForm::configure($schema);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return CostCenterGroupsTable::configure($table);
    }

    #[Override]
    public static function getLabel(): string
    {
        return __('labels.cost_center_group');
    }

    #[Override]
    public static function getPluralLabel(): string
    {
        return __('labels.cost_center_groups');
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ListCostCenterGroups::route('/'),
            'create' => CreateCostCenterGroup::route('/create'),
            'edit' => EditCostCenterGroup::route('/{record}/edit'),
        ];
    }
}
