<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Activities\Schemas;

use App\Filament\Admin\Labels\BillPeriodLabels;
use App\Models\CostCenter;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class ActivityForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                TextInput::make('name')
                    ->required(),
                TextInput::make('description'),
                DatePicker::make('start_date')
                    ->native(false)
                    ->required(),
                DatePicker::make('end_date')
                    ->native(false),
            ]),
            Section::make(__('labels.billing'))
                ->relationship('billableItem')
                ->schema([
                    Select::make('cost_center_id')
                        ->label(__('labels.cost_center'))
                        ->options(static fn () => CostCenter::query()->orderBy('number')->pluck('title', 'id'))
                        ->searchable()
                        ->preload()
                        ->required(),
                    TextInput::make('price')
                        ->label(__('labels.price'))
                        ->required(),
                    Select::make('bill_period')
                        ->label(__('labels.billing_period'))
                        ->options(BillPeriodLabels::options())
                        ->required(),
                ])
                ->mutateRelationshipDataBeforeSaveUsing(static fn (array $state) => [
                    ...$state,
                    'vat' => $state['price'] * 0.21,
                ])
                ->mutateRelationshipDataBeforeCreateUsing(static fn (array $state) => [
                    ...$state,
                    'vat' => $state['price'] * 0.21,
                ]),
        ]);
    }
}
