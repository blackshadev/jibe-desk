<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankingTransactions\Tables;

use App\Domain\BankTransactions\BankingTransactionReversalState;
use App\Domain\BankTransactions\BankTransactionStatus;
use App\Domain\BankTransactions\ResolveStatus;
use App\Filament\Admin\Resources\BankingTransactions\BankingTransactionResource;
use App\Models\BankingTransaction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
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
                    ->color(static fn (BankingTransaction $record): string => $record->amount < 0 ? 'danger' : 'success')
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
                    ->sortable()
                    ->alignEnd()
                    ->color(static fn (BankingTransaction $record): string => abs($record->unmatched_amount) >= 0.01 ? 'warning' : 'success'),
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
                TextColumn::make('resolve_status')
                    ->label(__('labels.resolve_status'))
                    ->badge()
                    ->formatStateUsing(static fn (ResolveStatus $state): string => match ($state) {
                        ResolveStatus::Unresolved => __('labels.resolve_status_unresolved'),
                        ResolveStatus::Resolved => __('labels.resolve_status_resolved'),
                        ResolveStatus::Unresolvable => __('labels.resolve_status_unresolvable'),
                    })
                    ->color(static fn (ResolveStatus $state): string => match ($state) {
                        ResolveStatus::Resolved => 'success',
                        ResolveStatus::Unresolvable => 'danger',
                        ResolveStatus::Unresolved => 'warning',
                    })
                    ->sortable(),
                TextColumn::make('banking_account_number')
                    ->label(__('labels.banking_account_number'))
                    ->searchable(),
                TextColumn::make('reversedByTransaction')
                    ->label(__('labels.reversal'))
                    ->formatStateUsing(static fn (BankingTransaction $record): ?string => match ($record->reversalState()) {
                        BankingTransactionReversalState::Reversal => __('labels.reversed_by', ['id' => $record->reversed_by_transaction_id]),
                        BankingTransactionReversalState::Reversed => __('labels.has_reversal', ['id' => $record->reversedTransaction->id]),
                        default => null,
                    })
                    ->color(static fn (BankingTransaction $record): string => $record->isReversal() || $record->isReversed() ? 'danger' : 'gray')
                    ->url(static fn (BankingTransaction $record): ?string => match ($record->reversalState()) {
                        BankingTransactionReversalState::Reversal => BankingTransactionResource::getUrl('view', ['record' => $record->reversed_by_transaction_id]),
                        BankingTransactionReversalState::Reversed => BankingTransactionResource::getUrl('view', ['record' => $record->reversedTransaction->id]),
                        default => null,
                    })
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label(__('labels.created_at'))
                    ->dateTime()
                    ->sortable()
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
