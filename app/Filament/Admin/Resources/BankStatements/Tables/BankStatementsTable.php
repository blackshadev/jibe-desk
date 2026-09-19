<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankStatements\Tables;

use App\Domain\BankStatements\StatementChainStatus;
use App\Domain\BankStatements\StatementIntegrityStatus;
use App\Filament\Admin\Resources\BankStatements\Actions\DetermineChainStatusAction;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class BankStatementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('bankAccount.name')
                    ->label(__('labels.bank_account'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('statement_number')
                    ->label(__('labels.statement_number'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('start_date')
                    ->label(__('labels.start_date'))
                    ->date()
                    ->sortable(),
                TextColumn::make('end_date')
                    ->label(__('labels.end_date'))
                    ->date()
                    ->sortable(),
                TextColumn::make('opening_balance')
                    ->label(__('labels.opening_balance'))
                    ->money('EUR')
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('closing_balance')
                    ->label(__('labels.closing_balance'))
                    ->money('EUR')
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('matched_percentage')
                    ->label(__('labels.matched_percentage'))
                    ->suffix('%')
                    ->alignEnd()
                    ->sortable()
                    ->color(static fn (?float $state): string => match (true) {
                        $state === null => 'gray',
                        $state >= 100 => 'success',
                        default => 'warning',
                    })
                    ->formatStateUsing(static fn (?float $state): string => $state === null ? '—' : (string) $state),
                TextColumn::make('integrity_status')
                    ->label(__('labels.integrity_status'))
                    ->badge()
                    ->formatStateUsing(static fn (StatementIntegrityStatus $state): string => match ($state) {
                        StatementIntegrityStatus::Valid => __('labels.integrity_statuses.valid'),
                        StatementIntegrityStatus::Mismatch => __('labels.integrity_statuses.mismatch'),
                    })
                    ->color(static fn (StatementIntegrityStatus $state): string => match ($state) {
                        StatementIntegrityStatus::Valid => 'success',
                        StatementIntegrityStatus::Mismatch => 'danger',
                    }),
                TextColumn::make('chain_status')
                    ->label(__('labels.chain_status'))
                    ->badge()
                    ->formatStateUsing(static fn (StatementChainStatus $state): string => match ($state) {
                        StatementChainStatus::Baseline => __('labels.chain_statuses.baseline'),
                        StatementChainStatus::Ok => __('labels.chain_statuses.ok'),
                        StatementChainStatus::Broken => __('labels.chain_statuses.broken'),
                    })
                    ->color(static fn (StatementChainStatus $state): string => match ($state) {
                        StatementChainStatus::Baseline => 'gray',
                        StatementChainStatus::Ok => 'success',
                        StatementChainStatus::Broken => 'danger',
                    }),
                TextColumn::make('currency')
                    ->label(__('labels.currency'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->modifyQueryUsing(static fn ($query) => $query->with('transactions'))
            ->defaultSort('end_date', 'desc')
            ->filters([
                SelectFilter::make('bank_account_id')
                    ->label(__('labels.bank_account'))
                    ->relationship('bankAccount', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ActionGroup::make([
                    DetermineChainStatusAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->authorizeIndividualRecords(),
                ]),
            ]);
    }
}
