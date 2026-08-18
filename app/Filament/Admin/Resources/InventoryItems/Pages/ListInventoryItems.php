<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\InventoryItems\Pages;

use App\Filament\Admin\Resources\InventoryItems\InventoryItemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Override;

final class ListInventoryItems extends ListRecords
{
    #[Override]
    protected static string $resource = InventoryItemResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
