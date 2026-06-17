<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\StorageSpaces\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
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
                TextColumn::make('currentRental.member.name')
                    ->label(__('labels.member'))
                    ->searchable(),
                TextColumn::make('currentRental.end_date')
                    ->label(__('labels.rented_until'))
                    ->date()
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('storage_space_location_id')
                    ->label(__('labels.location'))
                    ->relationship('location', 'name'),
            ], FiltersLayout::BeforeContent)
            ->defaultSort('location.name')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultGroup('location.name');
    }
}
