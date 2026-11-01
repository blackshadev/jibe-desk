<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\CostCenters\Schemas;

use App\Models\CostCenterGroup;
use Filament\Forms\Components\Select;
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
                        Select::make('cost_center_group_id')
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
                            ->required(),
                        Textarea::make('description')
                            ->label(__('labels.description'))
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
