<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Invoices\Actions;

use App\Domain\Invoices\InvoiceId;
use App\Domain\Invoices\InvoiceIdList;
use App\Domain\Invoices\InvoiceService;
use App\Models\Invoice;
use Filament\Actions\Action;
use Livewire\Component;

final class MarkAsPaidAction
{
    public static function make(): Action
    {
        return Action::make('markAsPaid')
            ->label(__('labels.mark_as_paid'))
            ->icon('heroicon-m-banknotes')
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription(__('labels.manual_mark_paid_warning'))
            ->modalIcon('heroicon-m-exclamation-triangle')
            ->modalIconColor('danger')
            ->visible(static fn (Invoice $record) => auth()->user()?->can('mark-paid', $record) ?? false)
            ->action(static function (Invoice $record, InvoiceService $invoiceService): void {
                $invoiceService->markAsPaid(new InvoiceIdList([InvoiceId::create($record->id)]));
            })
            ->successNotificationTitle(__('notifications.invoice_status_updated'))
            ->after(static fn (Component $livewire) => $livewire->dispatch('refresh'));
    }
}
