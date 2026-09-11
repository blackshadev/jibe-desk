<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankingTransactions\Schemas;

use App\Domain\BankTransactions\BankTransactionStatus;
use App\Models\BankAccount;
use App\Models\BankingTransaction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
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
                        TextInput::make('status')
                            ->label(__('labels.status'))
                            ->disabled()
                            ->hiddenOn('create')
                            ->formatStateUsing(static fn (?BankingTransaction $record): string => match ($record?->status) {
                                BankTransactionStatus::Open => __('labels.open'),
                                BankTransactionStatus::Completed => __('labels.completed'),
                                default => '',
                            }),
                        Select::make('banking_account_number')
                            ->options(
                                static fn (): array => BankAccount::query()
                                    ->select('iban')
                                    ->distinct()
                                    ->pluck('iban', 'iban')
                                    ->toArray(),
                            )
                            ->label(__('labels.banking_account_number'))
                            ->required(),
                    ]),
            ]);
    }
}
