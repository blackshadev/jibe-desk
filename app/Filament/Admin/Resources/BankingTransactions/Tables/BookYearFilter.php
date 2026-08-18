<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankingTransactions\Tables;

use App\Models\BankingTransaction;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class BookYearFilter
{
    public static function make(string $name): SelectFilter
    {
        return SelectFilter::make($name)
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
            });
    }
}
