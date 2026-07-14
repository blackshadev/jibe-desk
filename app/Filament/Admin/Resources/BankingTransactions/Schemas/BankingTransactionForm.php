<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankingTransactions\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class BankingTransactionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns()
            ->components([
                Section::make(__('labels.banking_transaction_information'))
                    ->schema([
                        DatePicker::make('date')
                            ->label(__('labels.date'))
                            ->native(false)
                            ->format('d-m-Y')
                            ->required(),
                        TextInput::make('description')
                            ->label(__('labels.description'))
                            ->columnSpanFull()
                            ->required(),
                        TextInput::make('amount')
                            ->label(__('labels.price'))
                            ->prefix('€')
                            ->numeric()
                            ->required(),
                        TextInput::make('banking_account_number')
                            ->label(__('labels.banking_account_number'))
                            ->required(),
                    ]),
            ]);
    }
}
