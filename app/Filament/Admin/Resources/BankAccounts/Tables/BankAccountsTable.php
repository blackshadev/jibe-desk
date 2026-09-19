<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankAccounts\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class BankAccountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('labels.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('iban')
                    ->label(__('labels.iban'))
                    ->searchable(),
                TextColumn::make('bic')
                    ->label(__('labels.banking_bic'))
                    ->toggleable(),
                TextColumn::make('active')
                    ->label(__('labels.status'))
                    ->badge()
                    /** @mago-expect lint:no-boolean-flag-parameter */
                    ->formatStateUsing(static fn (bool $state): string => $state ? __('labels.active') : __('labels.inactive'))
                    /** @mago-expect lint:no-boolean-flag-parameter */
                    ->color(static fn (bool $state): string => $state ? 'success' : 'gray'),
            ])
            ->filters([])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
