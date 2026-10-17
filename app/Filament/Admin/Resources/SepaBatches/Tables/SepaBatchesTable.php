<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\SepaBatches\Tables;

use App\Domain\Invoices\SepaBatchStatus;
use App\Domain\SharedKernel\CompoundPrice;
use App\Filament\Admin\Labels\SepaBatchStatusLabels;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class SepaBatchesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('invoice_date')
                    ->label(__('labels.invoice_date'))
                    ->date()
                    ->sortable(),
                TextColumn::make('sepa_transfer_date')
                    ->label(__('labels.sepa_transfer_date'))
                    ->date()
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('labels.status'))
                    ->formatStateUsing(static fn (SepaBatchStatus $state) => SepaBatchStatusLabels::options()[$state->value]),
                TextColumn::make('invoice_count')
                    ->label(__('labels.invoice_count'))
                    ->sortable(),
                TextColumn::make('open_total')
                    ->label(__('labels.open_total'))
                    ->formatStateUsing(static fn (CompoundPrice $state) => (string) $state)
                    ->alignEnd(),
                TextColumn::make('total')
                    ->label(__('labels.total'))
                    ->formatStateUsing(static fn (CompoundPrice $state) => (string) $state)
                    ->alignEnd(),
                TextColumn::make('created_at')
                    ->label(__('labels.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label(__('labels.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ]);
    }
}
