<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\SepaBatches\RelationManagers;

use App\Domain\PurchaseOrders\PurchaseOrderStatus;
use App\Filament\Admin\Labels\PurchaseOrderStatusLabels;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Livewire\Attributes\On;
use Override;

final class SepaBatchPurchaseOrdersRelationManager extends RelationManager
{
    #[Override]
    protected static string $relationship = 'purchaseOrders';

    #[Override]
    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('date')
                    ->label(__('labels.date'))
                    ->date(),
                TextColumn::make('description')
                    ->label(__('labels.description'))
                    ->searchable(),
                TextColumn::make('creditor_name')
                    ->label(__('labels.creditor_name'))
                    ->searchable(),
                TextColumn::make('creditor_iban')
                    ->label(__('labels.iban')),
                TextColumn::make('total')
                    ->label(__('labels.total'))
                    ->alignEnd(),
                TextColumn::make('status')
                    ->label(__('labels.status'))
                    ->formatStateUsing(static fn (PurchaseOrderStatus $state) => PurchaseOrderStatusLabels::options()[$state->value]),
            ]);
    }

    #[Override]
    public static function getModelLabel(): string
    {
        return mb_strtolower(__('labels.purchase_order'));
    }

    #[Override]
    public static function getPluralModelLabel(): string
    {
        return mb_strtolower(__('labels.purchase_orders'));
    }

    #[On('refreshInvoicesTable')]
    public function refresh(): void
    {
    }
}
