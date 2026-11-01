<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\CostCenterGroups\Pages;

use App\Filament\Admin\Resources\CostCenterGroups\CostCenterGroupResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Override;

final class EditCostCenterGroup extends EditRecord
{
    #[Override]
    protected static string $resource = CostCenterGroupResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
