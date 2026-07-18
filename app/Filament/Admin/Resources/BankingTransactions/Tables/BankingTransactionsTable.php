<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankingTransactions\Tables;

use App\Models\BankingTransaction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class BankingTransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('date')
                    ->label(__('labels.date'))
                    ->sortable()
                    ->date(),
                TextColumn::make('description')
                    ->label(__('labels.description'))
                    ->searchable()
                    ->limit(60),
                TextColumn::make('amount')
                    ->label(__('labels.price'))
                    ->money('EUR')
                    ->sortable()
                    ->alignEnd()
                    ->color(static fn (BankingTransaction $record): string => $record->amount < 0 ? 'danger' : 'success'),
                TextColumn::make('unmatched_amount')
                    ->label(__('labels.unmatched_amount'))
                    ->money('EUR')
                    ->sortable()
                    ->alignEnd()
                    ->color(static fn (BankingTransaction $record): string => abs($record->unmatched_amount) >= 0.01 ? 'warning' : 'success'),
                TextColumn::make('banking_account_number')
                    ->label(__('labels.banking_account_number'))
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label(__('labels.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
