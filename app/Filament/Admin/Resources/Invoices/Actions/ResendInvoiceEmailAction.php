<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Invoices\Actions;

use App\Domain\Invoices\InvoiceId;
use App\Domain\Invoices\Jobs\SendInvoiceEmail;
use App\Domain\Invoices\SendInvoiceEmailData;
use App\Domain\Jobs\JobDispatcher;
use App\Models\Invoice;
use Filament\Actions\Action;
use Livewire\Component;

final class ResendInvoiceEmailAction
{
    public static function make(): Action
    {
        return Action::make('resendInvoiceEmail')
            ->label(__('labels.resend_invoice_email'))
            ->icon('heroicon-m-paper-airplane')
            ->requiresConfirmation()
            ->modalDescription(__('labels.resend_invoice_email_warning'))
            ->visible(static fn (Invoice $record) => auth()->user()?->can('resend-email', $record) ?? false)
            ->action(static function (Invoice $record, JobDispatcher $dispatcher): void {
                $dispatcher->dispatch(new SendInvoiceEmail(new SendInvoiceEmailData(
                    invoiceId: InvoiceId::create($record->id),
                    isResend: true,
                )));
            })
            ->successNotificationTitle(__('notifications.invoice_email_resent'))
            ->after(static fn (Component $livewire) => $livewire->dispatch('refresh'));
    }
}
