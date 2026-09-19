<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankTransactions\Tables;

use App\Models\BankTransaction;
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
            ->getKeyFromRecordUsing(static fn (BankTransaction $record): string => $record->date->format('Y-m'))
            ->getTitleFromRecordUsing(static fn (BankTransaction $record): string => $record->date->format('Y-m'))
            ->groupQueryUsing(static fn (QueryBuilder $query): QueryBuilder => $query->groupByRaw($monthExpression))
            ->orderQueryUsing(static fn (Builder $query, string $direction): Builder => $query->orderBy('date', 'desc'))
            ->scopeQueryByKeyUsing(static fn (Builder $query, ?string $key): Builder => (
                $key === null
                    ? $query
                    : $query->whereRaw($monthExpression . ' = ?', [$key])
            ));
    }
}
