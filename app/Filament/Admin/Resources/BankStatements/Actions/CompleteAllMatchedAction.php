<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankStatements\Actions;

use App\Domain\BankTransactions\BankTransactionId;
use App\Domain\BankTransactions\BankTransactionService;
use App\Domain\BankTransactions\BankTransactionStatus;
use App\Domain\PurchaseOrders\PurchaseOrderIncompleteException;
use App\Filament\Admin\Labels\PurchaseOrderProblemLabels;
use App\Models\BankStatement;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
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
            ->action(static function (RelationManager|Component|null $livewire, ?BankStatement $record, BankTransactionService $service, Action $action): void {
                $bankStatement = self::getBankStatement($livewire, $record);
                if ($bankStatement === null) {
                    return;
                }

                $problemDescriptions = [];

                $bankStatement
                    ->transactions
                    ->filter(
                        static fn ($transaction): bool => $transaction->status === BankTransactionStatus::Open && abs($transaction->unmatched_amount) < 0.01,
                    )
                    ->each(static function ($transaction) use ($service, &$problemDescriptions): void {
                        try {
                            $service->complete(BankTransactionId::create($transaction->id));
                        } catch (PurchaseOrderIncompleteException $exception) {
                            foreach (explode(PHP_EOL, PurchaseOrderProblemLabels::describeAll($exception->problems)) as $description) {
                                $problemDescriptions[] = $description;
                            }
                        }
                    });

                if ($problemDescriptions !== []) {
                    Notification::make()
                        ->title(__('notifications.purchase_order_incomplete'))
                        ->body(implode(PHP_EOL, $problemDescriptions))
                        ->danger()
                        ->send();

                    $action->failure();
                }
            })
            ->successNotificationTitle(__('labels.completed'))
            ->after(static fn (Component $livewire) => $livewire->dispatch('refresh'));
    }
}
