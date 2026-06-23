<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\CostCenters\Pages;

use App\Filament\Admin\Resources\CostCenters\CostCenterResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateCostCenter extends CreateRecord
{
    protected static string $resource = CostCenterResource::class;
}
