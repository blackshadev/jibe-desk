<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Households\Pages;

use App\Filament\Admin\Resources\Households\HouseholdResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Override;

final class ListHouseholds extends ListRecords
{
    protected static string $resource = HouseholdResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
