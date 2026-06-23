<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\StorageSpaceLocations\Schemas;

use App\Filament\Admin\Labels\BillPeriodLabels;
use App\Models\CostCenter;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class StorageSpaceLocationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                TextInput::make('name')
                    ->label(__('labels.name'))
                    ->required()
                    ->unique(),
            ]),
            Section::make(__('labels.billing'))
                ->relationship('billableItem')
                ->schema([
                    TextInput::make('description')
                        ->label(__('labels.description'))
                        ->required(),
                    TextInput::make('price')
                        ->label(__('labels.price'))
                        ->required(),
                    Select::make('bill_period')
                        ->label(__('labels.bill_period'))
                        ->options(BillPeriodLabels::options()),
                    Select::make('cost_center_id')
                        ->label(__('labels.cost_center'))
                        ->options(static fn () => CostCenter::query()->orderBy('number')->pluck('title', 'id'))
                        ->searchable()
                        ->preload()
                        ->required(),
                ]),
        ]);
    }
}
