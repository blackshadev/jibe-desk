<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Activities\Tables;

use App\Domain\Invoices\Billing\BillPeriod;
use App\Domain\Invoices\Formatters\PriceFormatter;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class ActivitiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('labels.name')),
                TextColumn::make('start_date')
                    ->label(__('labels.start_date'))
                    ->date(),
                TextColumn::make('end_date')
                    ->label(__('labels.end_date'))
                    ->date(),
                TextColumn::make('billableItem.price')
                    ->label(__('labels.price'))
                    ->formatStateUsing(PriceFormatter::format(...)),
                TextColumn::make('billableItem.bill_period')
                    ->label(__('labels.bill_period'))
                    ->formatStateUsing(static fn (BillPeriod $state) => __('labels.bill_periods.' . $state->value)),
            ]);
    }
}
