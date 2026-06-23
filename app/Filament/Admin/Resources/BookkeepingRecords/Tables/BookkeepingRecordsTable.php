<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BookkeepingRecords\Tables;

use App\Models\BookkeepingRecord;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BookkeepingRecordsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('year')
                    ->label(__('labels.book_year'))
                    ->sortable(),
                TextColumn::make('costCenter.title')
                    ->label(__('labels.cost_center'))
                    ->searchable(),
                TextColumn::make('amount')
                    ->label(__('labels.price'))
                    ->money('EUR')
                    ->sortable(),
                TextColumn::make('description')
                    ->label(__('labels.description'))
                    ->searchable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('year')
                    ->label(__('labels.book_year'))
                    ->options(
                        static fn () => BookkeepingRecord::query()
                            ->select('year')
                            ->distinct()
                            ->pluck('year', 'year')
                            ->toArray(),
                    ),
                SelectFilter::make('cost_center_id')
                    ->label(__('labels.cost_center'))
                    ->relationship('costCenter', 'title'),
            ])
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
