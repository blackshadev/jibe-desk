<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankTransactions\Actions;

use App\Domain\BankTransactions\BankTransactionId;
use App\Domain\BankTransactions\BankTransactionService;
use App\Domain\BankTransactions\BankTransactionStatus;
use App\Domain\PurchaseOrders\PurchaseOrderIncompleteException;
use App\Filament\Admin\Labels\PurchaseOrderProblemLabels;
use App\Models\BankTransaction;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Livewire\Component;

final class UnlinkReversalAction
{
    public static function make(): Action
    {
        return Action::make('unlinkReversal')
            ->label(__('labels.unlink_reversal'))
            ->icon('heroicon-o-link-slash')
            ->color('danger')
            ->visible(static fn (BankTransaction $record): bool => $record->isReversal() && $record->status === BankTransactionStatus::Open)
            ->requiresConfirmation()
            ->action(static function (
                BankTransaction $record,
                BankTransactionService $service,
            ): void {
                try {
                    $service->unlinkReversal(
                        BankTransactionId::create($record->id),
                    );
                } catch (PurchaseOrderIncompleteException $exception) {
                    Notification::make()
                        ->title(__('notifications.purchase_order_incomplete'))
                        ->body(PurchaseOrderProblemLabels::describeAll($exception->problems))
                        ->danger()
                        ->send();
                }
            })
            ->successNotificationTitle(__('labels.reversal_unlinked'))
            ->after(static fn (Component $livewire) => $livewire->dispatch('refresh'));
    }
}
