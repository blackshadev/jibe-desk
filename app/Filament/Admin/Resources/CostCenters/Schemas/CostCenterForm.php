<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\CostCenters\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class CostCenterForm
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
                            ->required()
                            ->unique(ignoreRecord: true),
                        TextInput::make('title')
                            ->label(__('labels.title'))
                            ->required(),
                        Textarea::make('description')
                            ->label(__('labels.description'))
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
