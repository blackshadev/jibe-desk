<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankingTransactions\Tables;

use App\Models\BankingTransaction;
use Filament\Tables\Grouping\Group;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

final class MonthGroup
{
    public static function make(string $key): Group
    {
        $monthExpression = DB::connection()->getConfig()['driver'] === 'pgsql'
            ? "to_char(date, 'YYYY-MM')"
            : "strftime('%Y-%m', date)";

        return Group::make($key)
            ->getKeyFromRecordUsing(static fn (BankingTransaction $record): string => $record->date->format('Y-m'))
            ->getTitleFromRecordUsing(static fn (BankingTransaction $record): string => $record->date->format('Y-m'))
            ->groupQueryUsing(static fn (QueryBuilder $query): QueryBuilder => $query->groupByRaw($monthExpression))
            ->orderQueryUsing(static fn (Builder $query, string $direction): Builder => $query->orderBy('date', $direction))
            ->scopeQueryByKeyUsing(static fn (Builder $query, ?string $key): Builder => (
                $key === null
                    ? $query
                    : $query->whereRaw($monthExpression . ' = ?', [$key])
            ));
    }
}
