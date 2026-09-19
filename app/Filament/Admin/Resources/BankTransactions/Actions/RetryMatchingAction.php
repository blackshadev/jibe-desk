<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankTransactions\Actions;

use App\Domain\BankTransactions\BankTransactionId;
use App\Domain\BankTransactions\BankTransactionIdList;
use App\Domain\BankTransactions\BankTransactionService;
use App\Domain\BankTransactions\BankTransactionStatus;
use App\Domain\BankTransactions\ResolveStatus;
use App\Models\BankTransaction;
use Filament\Actions\Action;
use Livewire\Component;

final class RetryMatchingAction
{
    public static function make(): Action
    {
        return Action::make('retryMatching')
            ->label(__('labels.retry_matching'))
            ->color('warning')
            ->icon('heroicon-o-arrow-path')
            ->visible(static fn (BankTransaction $record): bool => $record->resolve_status !== ResolveStatus::Resolved && $record->status === BankTransactionStatus::Open)
            ->requiresConfirmation()
            ->action(static function (
                BankTransaction $record,
                BankTransactionService $service,
            ): void {
                $bankTransactionId = BankTransactionId::create($record->id);

                $service->resolveMatching(new BankTransactionIdList([$bankTransactionId]));
            })
            ->successNotificationTitle(__('labels.retry_matching_completed'))
            ->after(static fn (Component $livewire) => $livewire->dispatch('refresh'));
    }
}
