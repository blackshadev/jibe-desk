<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\CostCenters\Tables;

use App\Models\CostCenterGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
                TextColumn::make('costCenterGroup.label')
                    ->label(__('labels.cost_center_group'))
                    ->sortable(false),
                TextColumn::make('description')
                    ->label(__('labels.description'))
                    ->limit(50)
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('cost_center_group_id')
                    ->label(__('labels.cost_center_group'))
                    ->options(
                        static fn (): array => CostCenterGroup::query()
                            ->orderBy('number')
                            ->get()
                            ->mapWithKeys(static fn (CostCenterGroup $group): array => [$group->id => $group->label])
                            ->all(),
                    )
                    ->searchable()
                    ->preload()
                    ->query(static function (Builder $query, array $data): Builder {
                        $groupId = $data['value'] ?? null;

                        if (blank($groupId)) {
                            return $query;
                        }

                        $group = CostCenterGroup::query()->find($groupId);

                        if ($group === null) {
                            return $query;
                        }

                        return $query->whereIn('cost_centers.cost_center_group_id', $group->selfAndDescendantIds());
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
