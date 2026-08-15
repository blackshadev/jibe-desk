<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\InventoryItems\Pages;

use App\Filament\Admin\Resources\InventoryCategories\InventoryCategoryResource;
use App\Filament\Admin\Resources\InventoryCategories\Pages\ListInventoryCategories;
use App\Filament\Admin\Resources\InventoryItems\InventoryItemResource;
use Filament\Actions\CreateAction;
use Filament\Navigation\NavigationItem;
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

    #[Override]
    public function getSubNavigation(): array
    {
        return [
            NavigationItem::make(InventoryCategoryResource::class)
                ->url(InventoryCategoryResource::getUrl('index'))
                ->label(InventoryCategoryResource::getNavigationLabel())
                ->icon(InventoryCategoryResource::getNavigationIcon()),

            NavigationItem::make(InventoryItemResource::class)
                ->url(InventoryItemResource::getUrl('index'))
                ->label(InventoryItemResource::getNavigationLabel())
                ->icon(InventoryItemResource::getNavigationIcon()),
        ];
    }
}
