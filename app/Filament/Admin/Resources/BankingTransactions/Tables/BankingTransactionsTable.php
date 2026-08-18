<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankingTransactions\Tables;

use App\Domain\BankTransactions\BankTransactionStatus;
use App\Domain\BankTransactions\ResolveStatus;
use App\Filament\Admin\Resources\BankingTransactions\BankingTransactionResource;
use App\Models\BankingTransaction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

final class BankingTransactionsTable
{
    public static function configure(Table $table): Table
    {
        $monthExpression = self::monthExpression();

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
                    ->formatStateUsing(static fn ($state): string => match ($state) {
                        'open' => __('labels.open'),
                        'completed' => __('labels.completed'),
                        default => $state instanceof BankTransactionStatus ? $state->value : (string) $state,
                    })
                    ->color(static fn ($state): string => match ($state) {
                        'open' => 'warning',
                        'completed' => 'success',
                        default => 'gray',
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
                    ->formatStateUsing(static fn (BankingTransaction $record): ?string => (
                        $record->isReversal()
                            ? __('labels.reversed_by', ['id' => $record->reversed_by_transaction_id])
                            : (
                                $record->isReversed()
                                    ? __('labels.has_reversal', ['id' => $record->reversedTransaction->id])
                                    : null
                            )
                    ))
                    ->color(static fn (BankingTransaction $record): string => $record->isReversal() || $record->isReversed() ? 'danger' : 'gray')
                    ->url(static fn (BankingTransaction $record): ?string => (
                        $record->isReversal()
                            ? BankingTransactionResource::getUrl('view', ['record' => $record->reversed_by_transaction_id])
                            : (
                                $record->isReversed()
                                    ? BankingTransactionResource::getUrl('view', ['record' => $record->reversedTransaction->id])
                                    : null
                            )
                    ))
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
                Group::make('month')
                    ->label(__('labels.month'))
                    ->getKeyFromRecordUsing(static fn (BankingTransaction $record): string => $record->date->format('Y-m'))
                    ->getTitleFromRecordUsing(static fn (BankingTransaction $record): string => $record->date->format('Y-m'))
                    ->groupQueryUsing(static fn (QueryBuilder $query): QueryBuilder => $query->groupByRaw($monthExpression))
                    ->orderQueryUsing(static fn (Builder $query, string $direction): Builder => $query->orderBy('date', $direction))
                    ->scopeQueryByKeyUsing(static fn (Builder $query, ?string $key): Builder => (
                        $key === null
                            ? $query
                            : $query->whereRaw($monthExpression . ' = ?', [$key])
                    )),
            ])
            ->defaultGroup('month')
            ->groupingSettingsHidden()
            ->filters([
                SelectFilter::make(__('labels.book_year'))
                    ->options(
                        BankingTransaction::query()
                            ->select(
                                DB::connection()->getConfig()['driver'] === 'pgsql'
                                    ? DB::raw('EXTRACT(YEAR FROM date) AS year')
                                    : DB::raw('STRFTIME(\'%Y\', date) AS year'),
                            )
                            ->pluck('year', 'year')
                            ->all(),
                    )
                    ->query(static function (Builder $query, array $state) {
                        $value = $state['value'] ?? '';
                        if ($value === '') {
                            return $query;
                        }

                        return $query->whereYear('date', $value);
                    }),
                SelectFilter::make('is_reversal')
                    ->label(__('labels.is_reversal'))
                    ->options([
                        '1' => __('labels.yes'),
                        '0' => __('labels.no'),
                    ])
                    ->query(static function (Builder $query, array $state) {
                        $value = $state['value'] ?? '';
                        if ($value === '') {
                            return $query;
                        }

                        return $value === '1'
                            ? $query->whereNotNull('reversed_by_transaction_id')
                            : $query->whereNull('reversed_by_transaction_id');
                    }),
            ])
            ->filtersLayout(FiltersLayout::BeforeContent);
    }

    /**
     * Raw SQL expression that returns the month (`YYYY-MM`) of the transaction date,
     * matching the `Y-m` format produced by `getKeyFromRecordUsing`.
     */
    private static function monthExpression(): string
    {
        return DB::connection()->getConfig()['driver'] === 'pgsql'
            ? "to_char(date, 'YYYY-MM')"
            : "strftime('%Y-%m', date)";
    }
}
