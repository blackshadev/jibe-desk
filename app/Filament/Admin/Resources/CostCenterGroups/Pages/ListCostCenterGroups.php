<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\CostCenterGroups\Pages;

use App\Filament\Admin\Resources\CostCenterGroups\CostCenterGroupResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Override;

final class ListCostCenterGroups extends ListRecords
{
    #[Override]
    protected static string $resource = CostCenterGroupResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
