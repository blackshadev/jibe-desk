<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankStatements\Actions;

use App\Domain\Bookkeeping\Banking\BankAccounts\BankAccountId;
use App\Domain\Bookkeeping\Banking\BankStatements\BankStatementId;
use App\Domain\Bookkeeping\Banking\BankStatements\DetermineBankStatementChainStatus;
use App\Domain\Bookkeeping\Banking\BankStatements\DetermineBankStatementChainStatusInput;
use App\Models\BankStatement;
use Filament\Actions\Action;
use Livewire\Component;

final class DetermineChainStatusAction
{
    public static function make(): Action
    {
        return Action::make('determineChainStatus')
            ->label(__('labels.determine_chain_status'))
            ->modalHeading(__('labels.determine_chain_status'))
            ->color('gray')
            ->icon('heroicon-o-arrow-path')
            ->requiresConfirmation()
            ->action(static function (BankStatement $record, DetermineBankStatementChainStatus $service): void {
                $service->determine(new DetermineBankStatementChainStatusInput(
                    id: BankStatementId::create($record->id),
                    accountId: BankAccountId::create($record->bank_account_id),
                    startDate: $record->start_date,
                    openingBalance: (float) $record->opening_balance,
                ));
            })
            ->successNotificationTitle(__('notifications.chain_status_updated'))
            ->after(static fn (Component $livewire) => $livewire->dispatch('refresh'));
    }
}
