<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankingTransactions\Actions;

use App\Domain\BankTransactions\BankTransactionId;
use App\Domain\BankTransactions\BankTransactionService;
use App\Domain\BankTransactions\BankTransactionStatus;
use App\Filament\Admin\Resources\BankingTransactions\Pages\ViewBankingTransaction;
use App\Models\BankingTransaction;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;

final class LinkReversalAction
{
    public static function make(): Action
    {
        return Action::make('linkReversal')
            ->label(__('labels.link_reversal'))
            ->icon('heroicon-o-link')
            ->color('warning')
            ->visible(static fn (BankingTransaction $record): bool => $record->status === BankTransactionStatus::Open && !$record->isReversal() && !$record->isReversed())
            ->schema([
                Select::make('reversal_transaction_id')
                    ->label(__('labels.reversal_transaction'))
                    ->options(
                        static fn (BankingTransaction $record): array => BankingTransaction::query()
                            ->where('id', '!=', $record->id)
                            ->whereNull('reversed_by_transaction_id')
                            ->where('banking_account_number', $record->banking_account_number)
                            ->whereRaw('ABS(amount + ?) <= 0.01', [$record->amount])
                            ->orderBy('date')
                            ->get()
                            ->mapWithKeys(static fn (BankingTransaction $bt): array => [
                                $bt->id => sprintf(
                                    '#%d — %s — €%s',
                                    $bt->id,
                                    $bt->date->format('Y-m-d'),
                                    number_format($bt->amount, 2),
                                ),
                            ])
                            ->all(),
                    )
                    ->searchable()
                    ->required(),
            ])
            ->action(static function (
                BankingTransaction $record,
                array $data,
                BankTransactionService $service,
            ): void {
                $service->linkReversal(
                    BankTransactionId::create($record->id),
                    BankTransactionId::create((int) $data['reversal_transaction_id']),
                );
            })
            ->successNotificationTitle(__('labels.reversal_linked'))
            ->after(static fn (ViewBankingTransaction $livewire) => $livewire->dispatch('refresh'));
    }
}
