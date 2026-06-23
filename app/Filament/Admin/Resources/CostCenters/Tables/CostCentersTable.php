<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\CostCenters\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class CostCentersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')
                    ->label(__('labels.number'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('title')
                    ->label(__('labels.title'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('description')
                    ->label(__('labels.description'))
                    ->limit(50)
                    ->toggleable(),
            ])
            ->filters([])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
