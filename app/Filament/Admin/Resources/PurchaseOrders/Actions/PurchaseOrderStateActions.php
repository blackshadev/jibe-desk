<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PurchaseOrders\Actions;

use App\Domain\PurchaseOrders\PurchaseOrderId;
use App\Domain\PurchaseOrders\PurchaseOrderService;
use App\Domain\PurchaseOrders\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use Filament\Actions\Action;
use Filament\Resources\Pages\Page;

final class PurchaseOrderStateActions
{
    public static function make(): array
    {
        return [
            Action::make('markAsPending')
                ->label(__('labels.mark_as_pending'))
                ->icon('heroicon-m-clock')
                ->color('warning')
                ->requiresConfirmation()
                ->visible(static fn (PurchaseOrder $record): bool => $record->status === PurchaseOrderStatus::Open)
                ->action(static function (PurchaseOrder $record, PurchaseOrderService $service): void {
                    $service->markAsPending(PurchaseOrderId::create($record->id));
                })
                ->after(static fn (Page $livewire) => $livewire->dispatch('markedAsPending'))
                ->successNotificationTitle(__('notifications.purchase_order_marked_pending')),

            Action::make('markAsPaid')
                ->label(__('labels.mark_as_paid'))
                ->icon('heroicon-m-banknotes')
                ->color('success')
                ->requiresConfirmation()
                ->visible(static fn (PurchaseOrder $record): bool => $record->status === PurchaseOrderStatus::Pending)
                ->action(static function (PurchaseOrder $record, PurchaseOrderService $service): void {
                    $service->markAsPaid(PurchaseOrderId::create($record->id));
                })
                ->after(static fn (Page $livewire) => $livewire->dispatch('markedAsPaid'))
                ->successNotificationTitle(__('notifications.purchase_order_marked_paid')),
        ];
    }
}
