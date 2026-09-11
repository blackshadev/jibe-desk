<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankStatements\Actions;

use App\Domain\BankTransactions\BankTransactionId;
use App\Domain\BankTransactions\BankTransactionService;
use App\Domain\BankTransactions\BankTransactionStatus;
use App\Filament\Admin\Resources\BankStatements\Pages\ViewBankStatement;
use App\Models\BankStatement;
use Filament\Actions\Action;

final class CompleteAllMatchedAction
{
    public static function make(): Action
    {
        return Action::make('completeAllMatched')
            ->label(__('labels.complete_all_matched'))
            ->modalHeading(__('labels.complete_all_matched'))
            ->color('success')
            ->icon('heroicon-o-check-circle')
            ->requiresConfirmation()
            ->action(static function (BankStatement $record, BankTransactionService $service): void {
                $record
                    ->transactions
                    ->filter(
                        static fn ($transaction): bool => $transaction->status === BankTransactionStatus::Open && abs($transaction->unmatched_amount) < 0.01,
                    )
                    ->each(static function ($transaction) use ($service): void {
                        $service->complete(BankTransactionId::create($transaction->id));
                    });
            })
            ->successNotificationTitle(__('labels.completed'))
            ->after(static fn (ViewBankStatement $livewire) => $livewire->dispatch('refresh'));
    }
}
