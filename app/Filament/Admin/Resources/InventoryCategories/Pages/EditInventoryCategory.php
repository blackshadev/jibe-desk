<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\InventoryCategories\Pages;

use App\Filament\Admin\Resources\InventoryCategories\InventoryCategoryResource;
use App\Models\InventoryCategory;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Override;

final class EditInventoryCategory extends EditRecord
{
    #[Override]
    protected static string $resource = InventoryCategoryResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(static fn (InventoryCategory $record): bool => $record->inventoryItems()->count() === 0),
        ];
    }
}
