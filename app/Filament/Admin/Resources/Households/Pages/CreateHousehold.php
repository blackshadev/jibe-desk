<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Households\Pages;

use App\Filament\Admin\Resources\Households\HouseholdResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;

final class CreateHousehold extends CreateRecord
{
    protected static string $resource = HouseholdResource::class;
}
