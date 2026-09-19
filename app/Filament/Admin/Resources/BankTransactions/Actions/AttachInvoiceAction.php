<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankTransactions\Actions;

use App\Domain\BankTransactions\BankTransactionId;
use App\Domain\BankTransactions\BankTransactionService;
use App\Domain\Invoices\InvoiceId;
use App\Filament\Admin\Resources\BankTransactions\Helpers\GetTransaction;
use App\Filament\Admin\Resources\BankTransactions\Helpers\IsOpen;
use App\Models\BankStatement;
use App\Models\BankTransaction;
use App\Models\Invoice;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;

final class AttachInvoiceAction
{
    public static function make(): Action
    {
        return Action::make('attachInvoice')
            ->label(__('labels.attach_invoice'))
            ->icon(Heroicon::DocumentCurrencyEuro)
            ->modalHeading(__('labels.attach_invoice'))
            ->visible(IsOpen::checkOwner(...))
            ->schema([
                Select::make('invoice_id')
                    ->label(__('labels.invoice'))
                    ->options(static function (RelationManager $livewire, BankTransaction|BankStatement|null $record) {
                        $model = GetTransaction::get($livewire, $record);

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
            ->action(static function (array $data, RelationManager $livewire, BankTransaction|BankStatement|null $record, BankTransactionService $service): void {
                $record = GetTransaction::get($livewire, $record);

                $service->attachInvoice(
                    BankTransactionId::create($record->id),
                    InvoiceId::create((int) $data['invoice_id']),
                );
            })
            ->successNotificationTitle(__('labels.attached'));
    }
}
