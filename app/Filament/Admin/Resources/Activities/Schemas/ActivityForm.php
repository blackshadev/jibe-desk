<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Activities\Schemas;

use App\Domain\Invoices\Billing\BillPeriod;
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
                    TextInput::make('description')
                        ->required(),
                    TextInput::make('price')
                        ->required(),
                    Select::make('bill_period')
                        ->options([
                            BillPeriod::Monthly->value => __('labels.bill_periods.monthly'),
                            BillPeriod::Quarterly->value => __('labels.bill_periods.quarterly'),
                            BillPeriod::Annually->value => __('labels.bill_periods.annually'),
                        ])
                        ->required(),
                ]),
        ]);
    }
}
