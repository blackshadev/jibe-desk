<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankingTransactions\Actions;

use App\Domain\BankTransactions\BankTransactionId;
use App\Domain\BankTransactions\BankTransactionService;
use App\Domain\Invoices\InvoiceId;
use App\Filament\Admin\Resources\BankingTransactions\Helpers\IsOpen;
use App\Models\BankingTransaction;
use App\Models\Invoice;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;

final class AttachInvoiceAction
{
    public static function make(): Action
    {
        return Action::make('attachInvoice')
            ->label(__('labels.attach_invoice'))
            ->modalHeading(__('labels.attach_invoice'))
            ->visible(IsOpen::checkOwner(...))
            ->schema([
                Select::make('invoice_id')
                    ->label(__('labels.invoice'))
                    ->options(static function (RelationManager $livewire) {
                        /** @var BankingTransaction $model */
                        $model = $livewire->getOwnerRecord();

                        return Invoice::query()
                            ->openOrPending()
                            ->orderByAmountProximity((float) $model->amount)
                            ->with(['member'])
                            ->get()
                            ->mapWithKeys(static fn (Invoice $invoice): array => [
                                $invoice->id => $invoice->displayName,
                            ]);
                    })
                    ->searchable()
                    ->preload()
                    ->required(),
            ])
            ->action(static function (array $data, RelationManager $livewire, BankTransactionService $service): void {
                /** @var BankingTransaction $record */
                $record = $livewire->getOwnerRecord();

                $service->attachInvoice(
                    BankTransactionId::create($record->id),
                    InvoiceId::create((int) $data['invoice_id']),
                );
            })
            ->successNotificationTitle(__('labels.attached'));
    }
}
