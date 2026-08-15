<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\InventoryItems\Tables;

use App\Domain\Invoices\Formatters\PriceFormatter;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class InventoryItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('date')
                    ->label(__('labels.date'))
                    ->sortable()
                    ->date(),
                TextColumn::make('description')
                    ->label(__('labels.description'))
                    ->searchable()
                    ->limit(50)
                    ->toggleable(),
                TextColumn::make('inventoryCategory.name')
                    ->label(__('labels.category'))
                    ->sortable()
                    ->toggleable(false)
                    ->searchable(),
                TextColumn::make('amount')
                    ->label(__('labels.purchase_price'))
                    ->formatStateUsing(PriceFormatter::format(...))
                    ->sortable()
                    ->alignEnd(),
                TextColumn::make('residual_value')
                    ->label(__('labels.residual_value'))
                    ->formatStateUsing(PriceFormatter::format(...))
                    ->alignEnd(),
            ])
            ->filters([
                SelectFilter::make('inventory_category_id')
                    ->label(__('labels.category'))
                    ->relationship('inventoryCategory', 'name'),
            ], FiltersLayout::BeforeContent)
            ->defaultSort('date', 'desc');
    }
}
