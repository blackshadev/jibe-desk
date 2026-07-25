<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankingTransactions\Actions;

use App\Domain\BankTransactions\BankTransactionId;
use App\Domain\BankTransactions\BankTransactionIdList;
use App\Domain\BankTransactions\BankTransactionService;
use App\Domain\BankTransactions\BankTransactionStatus;
use App\Domain\BankTransactions\ResolveStatus;
use App\Filament\Admin\Resources\BankingTransactions\Pages\ViewBankingTransaction;
use App\Models\BankingTransaction;
use Filament\Actions\Action;

final class RetryMatchingAction
{
    public static function make(): Action
    {
        return Action::make('retryMatching')
            ->label(__('labels.retry_matching'))
            ->color('warning')
            ->icon('heroicon-o-arrow-path')
            ->visible(static fn (BankingTransaction $record): bool => $record->resolve_status !== ResolveStatus::Resolved && $record->status === BankTransactionStatus::Open)
            ->requiresConfirmation()
            ->action(static function (
                BankingTransaction $record,
                BankTransactionService $service,
            ): void {
                $bankTransactionId = BankTransactionId::create($record->id);

                $service->resolveMatching(new BankTransactionIdList([$bankTransactionId]));
            })
            ->successNotificationTitle(__('labels.retry_matching_completed'))
            ->after(static fn (ViewBankingTransaction $livewire) => $livewire->dispatch('refresh'));
    }
}
