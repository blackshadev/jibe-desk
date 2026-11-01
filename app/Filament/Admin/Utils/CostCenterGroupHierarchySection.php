<?php

declare(strict_types=1);

namespace App\Filament\Admin\Utils;

use App\Models\CostCenterGroup;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;

final class CostCenterGroupHierarchySection
{
    public static function make(CostCenterGroup $group): Section
    {
        return Section::make(__('labels.cost_center_group_hierarchy'))
            ->columnSpanFull()
            ->schema([
                View::make('filament.admin.utils.cost-center-group-hierarchy')
                    ->viewData(['groups' => [...$group->ancestorChain(), $group]]),
            ]);
    }
}
