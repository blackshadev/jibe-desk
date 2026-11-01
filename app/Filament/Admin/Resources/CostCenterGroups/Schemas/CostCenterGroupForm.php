<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\CostCenterGroups\Schemas;

use App\Models\CostCenterGroup;
use App\Rules\CostCenterGroupIsNotOwnAncestor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class CostCenterGroupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('number')
                            ->label(__('labels.number'))
                            ->helperText(__('labels.cost_center_group_hint'))
                            ->required()
                            ->unique(ignoreRecord: true),
                        TextInput::make('external_number')
                            ->label(__('labels.cost_center_group_external_number'))
                            ->required(),
                        TextInput::make('name')
                            ->label(__('labels.name'))
                            ->required(),
                        Select::make('parent_id')
                            ->label(__('labels.cost_center_group_parent'))
                            ->options(static function (?CostCenterGroup $record): array {
                                $query = CostCenterGroup::query()->orderBy('number');

                                if ($record?->exists) {
                                    $query->whereNotIn('id', $record->selfAndDescendantIds());
                                }

                                return $query
                                    ->get()
                                    ->mapWithKeys(static fn (CostCenterGroup $group): array => [$group->id => $group->label])
                                    ->all();
                            })
                            ->rules([
                                static fn (?CostCenterGroup $record): CostCenterGroupIsNotOwnAncestor => new CostCenterGroupIsNotOwnAncestor($record),
                            ])
                            ->searchable()
                            ->preload(),
                    ]),
            ]);
    }
}
