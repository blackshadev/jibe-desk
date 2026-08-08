<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankingTransactions\Actions;

use App\Domain\BankTransactions\BankTransactionId;
use App\Domain\BankTransactions\BankTransactionService;
use App\Domain\BankTransactions\BankTransactionStatus;
use App\Filament\Admin\Resources\BankingTransactions\Pages\ViewBankingTransaction;
use App\Models\BankingTransaction;
use Filament\Actions\Action;

final class UnlinkReversalAction
{
    public static function make(): Action
    {
        return Action::make('unlinkReversal')
            ->label(__('labels.unlink_reversal'))
            ->icon('heroicon-o-link-slash')
            ->color('danger')
            ->visible(static fn (BankingTransaction $record): bool => $record->isReversal() && $record->status === BankTransactionStatus::Open)
            ->requiresConfirmation()
            ->action(static function (
                BankingTransaction $record,
                BankTransactionService $service,
            ): void {
                $service->unlinkReversal(
                    BankTransactionId::create($record->id),
                );
            })
            ->successNotificationTitle(__('labels.reversal_unlinked'))
            ->after(static fn (ViewBankingTransaction $livewire) => $livewire->dispatch('refresh'));
    }
}
