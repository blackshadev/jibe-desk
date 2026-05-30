<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Invoices\Tables;

use App\Domain\Invoices\CompoundPrice;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class InvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('member.name')
                    ->label(__('labels.member'))
                    ->searchable(),
                TextColumn::make('date')
                    ->label(__('labels.invoice_date'))
                    ->sortable()
                    ->date(),
                TextColumn::make('invoice_number')
                    ->label(__('labels.invoice_number'))
                    ->searchable(),
                TextColumn::make('date')
                    ->label(__('labels.invoice_date'))
                    ->date(),
                TextColumn::make('total')
                    ->label(__('labels.total'))
                    ->formatStateUsing(static fn (CompoundPrice $state) => (string)$state)
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
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
