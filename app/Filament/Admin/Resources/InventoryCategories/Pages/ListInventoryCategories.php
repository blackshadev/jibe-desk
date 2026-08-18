<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\InventoryCategories\Pages;

use App\Filament\Admin\Resources\InventoryCategories\InventoryCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Override;

final class ListInventoryCategories extends ListRecords
{
    #[Override]
    protected static string $resource = InventoryCategoryResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    #[Override]
    public function getTitle(): string
    {
        return __('labels.inventory_categories');
    }
}
