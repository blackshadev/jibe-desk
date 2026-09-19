<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankTransactions\Tables;

use App\Domain\BankTransactions\BankTransactionReversalState;
use App\Domain\BankTransactions\BankTransactionStatus;
use App\Filament\Admin\Resources\BankTransactions\BankTransactionResource;
use App\Models\BankTransaction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;

final class BankTransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('date')
                    ->label(__('labels.date'))
                    ->date(),
                TextColumn::make('description')
                    ->label(__('labels.description'))
                    ->searchable()
                    ->limit(60),
                TextColumn::make('amount')
                    ->label(__('labels.price'))
                    ->money('EUR')
                    ->alignEnd()
                    ->color(static fn (BankTransaction $record): string => $record->amount < 0 ? 'danger' : 'success')
                    ->summarize([
                        Sum::make('total_amount')
                            ->label(__('labels.total_amount_this_month'))
                            ->money('EUR'),
                        RunningTotalSummery::make('running_total')
                            ->label(__('labels.running_total')),
                    ]),

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
                    }),
                TextColumn::make('banking_account_number')
                    ->label(__('labels.banking_account_number'))
                    ->searchable(),
                TextColumn::make('reversedByTransaction')
                    ->label(__('labels.reversal'))
                    ->formatStateUsing(static fn (BankTransaction $record): ?string => match ($record->reversalState()) {
                        BankTransactionReversalState::Reversal => __('labels.reversed_by', ['id' => $record->reversed_by_transaction_id]),
                        BankTransactionReversalState::Reversed => __('labels.has_reversal', ['id' => $record->reversedTransaction->id]),
                        default => null,
                    })
                    ->color(static fn (BankTransaction $record): string => $record->isReversal() || $record->isReversed() ? 'danger' : 'gray')
                    ->url(static fn (BankTransaction $record): ?string => match ($record->reversalState()) {
                        BankTransactionReversalState::Reversal => BankTransactionResource::getUrl('view', ['record' => $record->reversed_by_transaction_id]),
                        BankTransactionReversalState::Reversed => BankTransactionResource::getUrl('view', ['record' => $record->reversedTransaction->id]),
                        default => null,
                    })
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label(__('labels.created_at'))
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->authorizeIndividualRecords(),
                ]),
            ])
            ->groups([
                MonthGroup::make('month')
                    ->label(__('labels.month')),
            ])
            ->defaultGroup('month')
            ->groupingSettingsHidden()
            ->filters([
                BookYearFilter::make('book_year')
                    ->label(__('labels.book_year')),
                IsReversalFilter::make('is_reversal')
                    ->label(__('labels.is_reversal')),
            ])
            ->filtersLayout(FiltersLayout::BeforeContent);
    }
}
