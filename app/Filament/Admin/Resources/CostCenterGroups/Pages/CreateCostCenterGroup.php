<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\CostCenterGroups\Pages;

use App\Filament\Admin\Resources\CostCenterGroups\CostCenterGroupResource;
use Filament\Resources\Pages\CreateRecord;
use Override;

final class CreateCostCenterGroup extends CreateRecord
{
    #[Override]
    protected static string $resource = CostCenterGroupResource::class;
}
