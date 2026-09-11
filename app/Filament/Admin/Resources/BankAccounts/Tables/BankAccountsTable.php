<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankAccounts\Tables;

use App\Domain\BankAccounts\BankAccountBalanceRepository;
use App\Models\BankAccount;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

final class BankAccountsTable
{
    public static function configure(Table $table): Table
    {
        $overviewByAccount = self::balanceOverviewByAccount();

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
                TextColumn::make('expected_closing_balance')
                    ->label(__('labels.expected_closing_balance'))
                    ->money('EUR')
                    ->alignEnd()
                    ->state(static fn (BankAccount $record): ?float => $overviewByAccount->get($record->id)?->expectedClosing),
            ])
            ->filters([])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /** @return Collection<int, \App\Domain\BankAccounts\BankAccountBalanceOverview> */
    private static function balanceOverviewByAccount(): Collection
    {
        $overviews = app(BankAccountBalanceRepository::class)->getOverviewForYear(now()->year);

        return collect($overviews)->keyBy('bankAccountId');
    }
}
