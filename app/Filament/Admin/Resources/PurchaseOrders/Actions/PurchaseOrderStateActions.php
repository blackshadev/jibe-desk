<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PurchaseOrders\Actions;

use App\Domain\PurchaseOrders\PurchaseOrderIdList;
use App\Domain\PurchaseOrders\PurchaseOrderIncompleteException;
use App\Domain\PurchaseOrders\PurchaseOrderService;
use App\Filament\Admin\Labels\PurchaseOrderProblemLabels;
use App\Filament\Admin\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
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
                ->visible(static fn (PurchaseOrder $record): bool => auth()->user()->can('markAsPending', $record))
                ->action(static function (PurchaseOrder $record, PurchaseOrderService $service, Action $action): void {
                    try {
                        $service->markAsPending(PurchaseOrderIdList::fromArray([$record->id]));
                    } catch (PurchaseOrderIncompleteException $exception) {
                        Notification::make()
                            ->title(__('notifications.purchase_order_incomplete'))
                            ->body(PurchaseOrderProblemLabels::describeAll($exception->problems))
                            ->danger()
                            ->send();
                        $action->failure();
                    }
                })
                ->successRedirectUrl(static fn (Page $livewire, PurchaseOrder $record) => (
                    $livewire instanceof EditRecord ? PurchaseOrderResource::getUrl('view', ['record' => $record]) : null
                ))
                ->after(static fn (Page $livewire) => $livewire->dispatch('markedAsPending'))
                ->successNotificationTitle(__('notifications.purchase_order_marked_pending')),

            Action::make('markAsPaid')
                ->label(__('labels.mark_as_paid'))
                ->icon('heroicon-m-banknotes')
                ->color('success')
                ->requiresConfirmation()
                ->visible(static fn (PurchaseOrder $record): bool => auth()->user()->can('markAsPaid', $record))
                ->action(static function (PurchaseOrder $record, PurchaseOrderService $service): void {
                    try {
                        $service->markAsPaid(PurchaseOrderIdList::fromArray([$record->id]));
                    } catch (PurchaseOrderIncompleteException $exception) {
                        Notification::make()
                            ->title(__('notifications.purchase_order_incomplete'))
                            ->body(PurchaseOrderProblemLabels::describeAll($exception->problems))
                            ->danger()
                            ->send();
                    }
                })
                ->after(static fn (Page $livewire) => $livewire->dispatch('markedAsPaid'))
                ->successNotificationTitle(__('notifications.purchase_order_marked_paid')),

            Action::make('markAsDeclined')
                ->label(__('labels.mark_as_declined'))
                ->icon('heroicon-m-x-circle')
                ->color('danger')
                ->modalHeading(__('labels.mark_as_declined'))
                ->modalDescription(__('labels.manual_mark_purchase_order_declined_warning'))
                ->schema([
                    Textarea::make('declined_reason')
                        ->label(__('labels.declined_reason'))
                        ->rows(4)
                        ->required(),
                ])
                ->visible(static fn (PurchaseOrder $record): bool => auth()->user()->can('markAsDeclined', $record))
                ->action(static function (PurchaseOrder $record, PurchaseOrderService $service, array $data): void {
                    $service->markAsDeclined(
                        PurchaseOrderIdList::fromArray([$record->id]),
                        (string) $data['declined_reason'],
                    );
                })
                ->successRedirectUrl(static fn (Page $livewire, PurchaseOrder $record) => (
                    $livewire instanceof EditRecord ? PurchaseOrderResource::getUrl('view', ['record' => $record]) : null
                ))
                ->after(static fn (Page $livewire) => $livewire->dispatch('markedAsDeclined'))
                ->successNotificationTitle(__('notifications.purchase_order_marked_declined')),
        ];
    }
}
