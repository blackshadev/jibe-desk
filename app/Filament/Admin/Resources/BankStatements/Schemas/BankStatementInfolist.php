<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankStatements\Schemas;

use App\Domain\BankStatements\StatementChainStatus;
use App\Domain\BankStatements\StatementIntegrityStatus;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class BankStatementInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('labels.bank_statement'))
                    ->schema([
                        TextEntry::make('bankAccount.name')
                            ->label(__('labels.bank_account')),
                        TextEntry::make('statement_number')
                            ->label(__('labels.statement_number')),
                        TextEntry::make('start_date')
                            ->label(__('labels.start_date'))
                            ->date(),
                        TextEntry::make('end_date')
                            ->label(__('labels.end_date'))
                            ->date(),
                        TextEntry::make('currency')
                            ->label(__('labels.currency')),

                    ]),
                Section::make(__('labels.opening_balance'))
                    ->schema([
                        TextEntry::make('opening_balance')
                            ->label(__('labels.opening_balance'))
                            ->money('EUR'),
                        TextEntry::make('closing_balance')
                            ->label(__('labels.closing_balance'))
                            ->money('EUR'),
                        TextEntry::make('integrity_status')
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
                        TextEntry::make('chain_status')
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

                        TextEntry::make('matched_percentage')
                            ->label(__('labels.matched_percentage'))
                            ->suffix('%')
                            ->color(static fn (?float $state): string => match (true) {
                                $state === null => 'gray',
                                $state >= 100 => 'success',
                                default => 'warning',
                            })
                            ->formatStateUsing(static fn (?float $state): string => $state === null ? '—' : (string) $state),
                    ]),
            ]);
    }
}
