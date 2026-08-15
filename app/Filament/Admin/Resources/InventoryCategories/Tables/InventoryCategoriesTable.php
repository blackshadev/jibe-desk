<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\InventoryCategories\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class InventoryCategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('labels.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('inventory_items_count')
                    ->label(__('labels.inventory_items'))
                    ->counts('inventoryItems')
                    ->badge(),
            ]);
    }
}
