<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\InventoryItems\Pages;

use App\Filament\Admin\Resources\InventoryItems\InventoryItemResource;
use Filament\Resources\Pages\CreateRecord;
use Override;

final class CreateInventoryItem extends CreateRecord
{
    #[Override]
    protected static string $resource = InventoryItemResource::class;
}
