<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankingTransactions\Tables;

use App\Models\BankingTransaction;
use Carbon\CarbonImmutable;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Illuminate\Database\Query\Builder as QueryBuilder;

final class RunningTotalSummery
{
    public static function make(string $id): Summarizer
    {
        return Summarizer::make($id)
            ->using(static function (QueryBuilder $query): string|float|int {
                $yearMonth = $query->getBindings()[0] ?? '';

                if ($yearMonth === '') {
                    return '';
                }

                $split = explode('-', $yearMonth);
                [$year, $month] = $split + [now()->year, now()->month];

                $date = CarbonImmutable::create((int) $year, (int) $month)->lastOfMonth();

                return BankingTransaction::query()
                    ->whereYear('date', $year)
                    ->where('date', '<=', $date)
                    ->sum('amount');
            })
            ->numeric()
            ->money('EUR');
    }
}
