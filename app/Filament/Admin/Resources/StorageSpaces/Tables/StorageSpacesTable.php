<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\StorageSpaces\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class StorageSpacesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('location.name')
                    ->label(__('labels.location'))
                    ->sortable()
                    ->searchable(),
                TextColumn::make('number')
                    ->label(__('labels.space_number'))
                    ->sortable()
                    ->numeric(),
            ])
            ->defaultSort('location.name')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
