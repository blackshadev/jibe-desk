<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\SepaBatches\Actions;

use App\Domain\Billing\PurchaseOrders\PurchaseOrderStatus;
use App\Filament\Admin\Resources\SepaBatches\Helpers\OnOpenSepaBatch;
use App\Models\PurchaseOrder;
use App\Models\SepaBatch;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Webmozart\Assert\Assert;

final class AddPurchaseOrdersAction
{
    public static function make(): Action
    {
        return Action::make('addPurchaseOrders')
            ->label(__('labels.add_purchase_orders'))
            ->icon('heroicon-m-plus')
            ->schema([
                Select::make('purchase_order_ids')
                    ->label(__('labels.purchase_orders'))
                    ->multiple()
                    ->searchable()
                    ->options(
                        static fn () => PurchaseOrder::query()
                            ->whereNull('sepa_batch_id')
                            ->where('status', PurchaseOrderStatus::Pending)
                            ->orderBy('date')
                            ->get()
                            ->mapWithKeys(static fn (PurchaseOrder $purchaseOrder) => [
                                $purchaseOrder->id => sprintf(
                                    '[%s] %s — %s',
                                    $purchaseOrder->date->format('Y-m-d'),
                                    $purchaseOrder->description,
                                    $purchaseOrder->total,
                                ),
                            ]),
                    ),
            ])
            ->requiresConfirmation()
            ->action(static function (array $data, RelationManager $livewire): void {
                $batch = $livewire->getOwnerRecord();
                Assert::isInstanceOf($batch, SepaBatch::class);

                PurchaseOrder::query()
                    ->whereIn('id', $data['purchase_order_ids'])
                    ->update(['sepa_batch_id' => $batch->id]);
            })
            ->successNotificationTitle(__('notifications.purchase_orders_added_to_batch'))
            ->after(static fn (RelationManager $livewire) => $livewire->dispatch('refresh'))
            ->visible(static function (RelationManager $livewire): bool {
                $batch = $livewire->getOwnerRecord();

                return $batch instanceof SepaBatch && OnOpenSepaBatch::make($batch);
            });
    }
}
