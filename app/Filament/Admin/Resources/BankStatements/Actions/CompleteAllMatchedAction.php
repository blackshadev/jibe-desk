<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankStatements\Actions;

use App\Domain\BankTransactions\BankTransactionId;
use App\Domain\BankTransactions\BankTransactionService;
use App\Domain\BankTransactions\BankTransactionStatus;
use App\Models\BankStatement;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Livewire\Component;

final class CompleteAllMatchedAction
{
    public static function getBankStatement(RelationManager|Component|null $livewire, ?BankStatement $record): ?BankStatement
    {
        if ($record instanceof BankStatement) {
            return $record;
        }
        if ($livewire instanceof RelationManager) {
            $ownerRecord = $livewire->getOwnerRecord();
            if ($ownerRecord instanceof BankStatement) {
                return $ownerRecord;
            }
        }

        return null;
    }

    public static function make(): Action
    {
        return Action::make('completeAllMatched')
            ->label(__('labels.complete_all_matched'))
            ->modalHeading(__('labels.complete_all_matched'))
            ->color('success')
            ->icon('heroicon-o-check-circle')
            ->requiresConfirmation()
            ->disabled(static function (RelationManager|Component|null $livewire, ?BankStatement $record): bool {
                $bankStatement = self::getBankStatement($livewire, $record);
                if ($bankStatement === null) {
                    return true;
                }

                return $bankStatement
                    ->transactions
                    ->filter(
                        static fn ($transaction): bool => $transaction->status === BankTransactionStatus::Open && abs($transaction->unmatched_amount) < 0.01,
                    )
                    ->isEmpty();
            })
            ->action(static function (RelationManager|Component|null $livewire, ?BankStatement $record, BankTransactionService $service): void {
                $bankStatement = self::getBankStatement($livewire, $record);
                if ($bankStatement === null) {
                    return;
                }

                $bankStatement
                    ->transactions
                    ->filter(
                        static fn ($transaction): bool => $transaction->status === BankTransactionStatus::Open && abs($transaction->unmatched_amount) < 0.01,
                    )
                    ->each(static function ($transaction) use ($service): void {
                        $service->complete(BankTransactionId::create($transaction->id));
                    });
            })
            ->successNotificationTitle(__('labels.completed'))
            ->after(static fn (Component $livewire) => $livewire->dispatch('refresh'));
    }
}
