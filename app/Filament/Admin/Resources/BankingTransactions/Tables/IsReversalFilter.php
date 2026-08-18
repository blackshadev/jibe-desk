<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankingTransactions\Tables;

use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

final class IsReversalFilter
{
    public static function make(string $name): SelectFilter
    {
        return SelectFilter::make($name)
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
            });
    }
}
