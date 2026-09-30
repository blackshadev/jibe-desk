<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Invoices\Actions;

use App\Domain\Invoices\InvoiceId;
use App\Domain\Invoices\InvoiceService;
use App\Models\Invoice;
use Filament\Actions\Action;
use Livewire\Component;

final class CreateCreditAction
{
    public static function make(): Action
    {
        return Action::make('createCredit')
            ->label(__('labels.create_credit_invoice'))
            ->icon('heroicon-m-arrow-uturn-left')
            ->color('warning')
            ->requiresConfirmation()
            ->modalDescription(__('labels.create_credit_invoice_warning'))
            ->visible(static fn (Invoice $record) => auth()->user()?->can('create-credit', $record) ?? false)
            ->action(static function (Invoice $record, InvoiceService $invoiceService): void {
                $invoiceService->createCredit(InvoiceId::create($record->id));
            })
            ->successNotificationTitle(__('notifications.credit_invoice_created'))
            ->after(static fn (Component $livewire) => $livewire->dispatch('refresh'));
    }
}
