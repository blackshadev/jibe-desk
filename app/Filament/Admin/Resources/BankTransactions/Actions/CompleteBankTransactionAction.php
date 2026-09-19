<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankTransactions\Actions;

use App\Domain\BankTransactions\BankTransactionId;
use App\Domain\BankTransactions\BankTransactionService;
use App\Models\BankTransaction;
use Filament\Actions\Action;
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
                $service->complete(BankTransactionId::create($record->id));
            })
            ->successNotificationTitle(__('labels.completed'))
            ->after(static fn (Component $livewire) => $livewire->dispatch('refresh'));
    }
}
