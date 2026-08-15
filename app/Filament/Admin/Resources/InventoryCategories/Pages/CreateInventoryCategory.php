<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\InventoryCategories\Pages;

use App\Filament\Admin\Resources\InventoryCategories\InventoryCategoryResource;
use Filament\Resources\Pages\CreateRecord;
use Override;

final class CreateInventoryCategory extends CreateRecord
{
    #[Override]
    protected static string $resource = InventoryCategoryResource::class;
}
