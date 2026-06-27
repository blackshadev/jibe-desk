<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PurchaseOrders\Tables;

use App\Domain\Invoices\CompoundPrice;
use App\Domain\PurchaseOrders\PurchaseOrderStatus;
use App\Filament\Admin\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Filament\Admin\Utils\ViewOrEdit;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class PurchaseOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_path')
                    ->label(__('labels.image'))
                    ->disk('local')
                    ->circular()
                    ->size(40)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('creditor_name')
                    ->label(__('labels.creditor_name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('description')
                    ->label(__('labels.description'))
                    ->searchable()
                    ->limit(50),
                TextColumn::make('date')
                    ->label(__('labels.date'))
                    ->sortable()
                    ->date(),
                TextColumn::make('status')
                    ->label(__('labels.status'))
                    ->formatStateUsing(static fn (PurchaseOrderStatus $state) => __('labels.purchase_order_status.' . $state->value))
                    ->sortable(),
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
            ])
            ->recordUrl(ViewOrEdit::route(PurchaseOrderResource::class))
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
