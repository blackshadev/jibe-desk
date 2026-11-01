<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\CostCenterGroups\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class CostCenterGroupsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')
                    ->label(__('labels.number'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label(__('labels.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('parent.label')
                    ->label(__('labels.cost_center_group_parent'))
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('cost_centers_count')
                    ->label(__('labels.cost_center_group_cost_centers_count'))
                    ->counts('costCenters')
                    ->badge()
                    ->sortable(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
