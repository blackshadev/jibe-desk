<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankingTransactions\Actions;

use App\Domain\BankTransactions\BankTransactionId;
use App\Domain\BankTransactions\BankTransactionRepository;
use App\Domain\Invoices\InvoiceId;
use App\Models\Invoice;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;

final class AttachInvoiceAction
{
    public static function make(): Action
    {
        return Action::make('attachInvoice')
            ->label(__('labels.attach_invoice'))
            ->modalHeading(__('labels.attach_invoice'))
            ->schema([
                Select::make('invoice_id')
                    ->label(__('labels.invoice'))
                    ->options(static fn () => Invoice::query()
                        ->orderBy('invoice_number')
                        ->get()
                        ->mapWithKeys(static fn (Invoice $invoice): array => [
                            $invoice->id => sprintf('#%s - %s', $invoice->invoice_number, $invoice->member->fullName ?? ''),
                        ]))
                    ->searchable()
                    ->preload()
                    ->required(),
            ])
            ->action(static function (array $data, mixed $record, BankTransactionRepository $repository): void {
                $repository->attachInvoice(
                    BankTransactionId::create((int) $record->id),
                    InvoiceId::create((int) $data['invoice_id']),
                );
            })
            ->successNotificationTitle(__('labels.attached'));
    }
}
