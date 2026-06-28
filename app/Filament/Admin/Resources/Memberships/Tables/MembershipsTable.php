<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Memberships\Tables;

use App\Domain\Invoices\Formatters\PriceFormatter;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class MembershipsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('adultBillableItem.price')
                    ->label(__('labels.price_adults'))
                    ->formatStateUsing(PriceFormatter::format(...)),
                TextColumn::make('kidsBillableItem.price')
                    ->label(__('labels.price_kids'))
                    ->formatStateUsing(PriceFormatter::format(...)),
                TextColumn::make('members_count')
                    ->label(__('labels.members_count'))
                    ->counts('members')
                    ->badge(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
