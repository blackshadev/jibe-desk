<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\CostCenters\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

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
                            ->maxValue(2100),
                        TextInput::make('starting_amount')
                            ->label(__('labels.starting_amount'))
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01),
                    ]),
            ]);
    }
}
