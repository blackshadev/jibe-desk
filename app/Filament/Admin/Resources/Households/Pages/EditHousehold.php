<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Households\Pages;

use App\Filament\Admin\Resources\Households\HouseholdResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Override;

final class EditHousehold extends EditRecord
{
    protected static string $resource = HouseholdResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
