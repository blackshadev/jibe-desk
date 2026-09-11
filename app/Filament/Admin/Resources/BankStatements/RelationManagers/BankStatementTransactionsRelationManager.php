<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankStatements\RelationManagers;

use App\Domain\BankTransactions\BankTransactionStatus;
use App\Filament\Admin\Resources\BankingTransactions\BankingTransactionResource;
use App\Models\BankingTransaction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Override;

final class BankStatementTransactionsRelationManager extends RelationManager
{
    #[Override]
    protected static string $relationship = 'transactions';

    #[Override]
    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('date')
                    ->label(__('labels.date'))
                    ->date()
                    ->sortable(),

                TextColumn::make('banking_account_number')
                    ->label(__('labels.banking_account_number'))
                    ->searchable(),
                TextColumn::make('description')
                    ->label(__('labels.description'))
                    ->searchable()
                    ->tooltip(static fn (BankingTransaction $record): string => $record->description)
                    ->limit(30),
                TextColumn::make('amount')
                    ->label(__('labels.price'))
                    ->money('EUR')
                    ->alignEnd()
                    ->color(static fn (BankingTransaction $record): string => $record->amount < 0 ? 'danger' : 'success'),

                TextColumn::make('unmatched_amount')
                    ->label(__('labels.unmatched'))
                    ->money('EUR')
                    ->alignEnd()
                    ->badge(static fn (float $state): bool => abs($state) < 0.01)
                    ->formatStateUsing(static fn (float $state): string => abs($state) >= 0.01 ? number_format($state, 2) : 'Niets')
                    ->color(static fn (float $state): string => abs($state) >= 0.01 ? 'warning' : 'success'),

                TextColumn::make('status')
                    ->label(__('labels.status'))
                    ->badge()
                    ->formatStateUsing(static fn (BankTransactionStatus $state): string => match ($state) {
                        BankTransactionStatus::Open => __('labels.open'),
                        BankTransactionStatus::Completed => __('labels.completed'),
                    })
                    ->color(static fn (BankTransactionStatus $state): string => match ($state) {
                        BankTransactionStatus::Open => 'warning',
                        BankTransactionStatus::Completed => 'success',
                    })
                    ->sortable(),
            ])
            ->recordUrl(static fn (BankingTransaction $record): string => BankingTransactionResource::getUrl('view', ['record' => $record]));
    }
}
