<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\MemberObjectTypes\Schemas;

use App\Filament\Admin\Labels\BillPeriodLabels;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class MemberObjectTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                TextInput::make('name')
                    ->label(__('labels.name'))
                    ->required(),
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
                ]),
        ]);
    }
}
