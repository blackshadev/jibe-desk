<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankTransactions\Actions;

use App\Domain\BankTransactions\BankTransactionId;
use App\Domain\BankTransactions\BankTransactionService;
use App\Domain\PurchaseOrders\PurchaseOrderIncompleteException;
use App\Filament\Admin\Labels\PurchaseOrderProblemLabels;
use App\Models\BankTransaction;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Livewire\Component;

final class CompleteBankTransactionAction
{
    public static function make(): Action
    {
        return Action::make('complete')
            ->label(__('labels.complete_transaction'))
            ->modalHeading(__('labels.complete_transaction'))
            ->color('success')
            ->icon('heroicon-o-check-circle')
            ->visible(static fn (BankTransaction $record): bool => !$record->isCompleted())
            ->disabled(static fn (BankTransaction $record): bool => $record->isCompleted() || abs($record->unmatched_amount) >= 0.01)
            ->requiresConfirmation()
            ->action(static function (BankTransaction $record, BankTransactionService $service): void {
                try {
                    $service->complete(BankTransactionId::create($record->id));
                } catch (PurchaseOrderIncompleteException $exception) {
                    Notification::make()
                        ->title(__('notifications.purchase_order_incomplete'))
                        ->body(PurchaseOrderProblemLabels::describeAll($exception->problems))
                        ->danger()
                        ->send();
                }
            })
            ->successNotificationTitle(__('labels.completed'))
            ->after(static fn (Component $livewire) => $livewire->dispatch('refresh'));
    }
}
