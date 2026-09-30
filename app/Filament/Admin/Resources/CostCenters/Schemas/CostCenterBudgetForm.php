<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\CostCenters\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

final class CostCenterBudgetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('year')
                            ->label(__('labels.book_year'))
                            ->required()
                            ->numeric()
                            ->minValue(2000)
                            ->maxValue(2100)
                            ->unique(
                                ignoreRecord: true,
                                modifyRuleUsing: static fn (Unique $rule, RelationManager $livewire): Unique => $rule->where(
                                    'cost_center_id',
                                    $livewire->getOwnerRecord()->getKey(),
                                ),
                            ),
                        TextInput::make('starting_amount')
                            ->label(__('labels.starting_amount'))
                            ->required()
                            ->numeric()
                            ->step(0.01),
                        TextInput::make('budget_amount')
                            ->label(__('labels.budget'))
                            ->required()
                            ->numeric()
                            ->step(0.01),
                    ]),
            ]);
    }
}
