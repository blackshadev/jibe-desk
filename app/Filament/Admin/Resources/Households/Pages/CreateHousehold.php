<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Households\Pages;

use App\Filament\Admin\Resources\Households\HouseholdResource;
use Filament\Resources\Pages\CreateRecord;
use Override;

final class CreateHousehold extends CreateRecord
{
    #[Override]
    protected static string $resource = HouseholdResource::class;
}
