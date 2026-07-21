<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankingTransactions\Actions;

use App\Domain\BankTransactions\BankTransactionId;
use App\Domain\BankTransactions\BankTransactionService;
use App\Filament\Admin\Resources\BankingTransactions\Pages\ViewBankingTransaction;
use App\Models\BankingTransaction;
use Filament\Actions\Action;

final class CompleteBankingTransactionAction
{
    public static function make(): Action
    {
        return Action::make('complete')
            ->label(__('labels.complete_transaction'))
            ->modalHeading(__('labels.complete_transaction'))
            ->color('success')
            ->icon('heroicon-o-check-circle')
            ->visible(static fn (BankingTransaction $record): bool => !$record->isCompleted())
            ->disabled(static fn (BankingTransaction $record): bool => $record->isCompleted() || abs($record->unmatched_amount) >= 0.01)
            ->requiresConfirmation()
            ->action(static function (BankingTransaction $record, BankTransactionService $service): void {
                $service->complete(BankTransactionId::create($record->id));
            })
            ->successNotificationTitle(__('labels.completed'))
            ->after(static fn (ViewBankingTransaction $livewire) => $livewire->dispatch('refresh'));
    }
}
