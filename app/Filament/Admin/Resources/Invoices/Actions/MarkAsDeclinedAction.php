<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Invoices\Actions;

use App\Domain\Invoices\InvoiceId;
use App\Domain\Invoices\InvoiceIdList;
use App\Domain\Invoices\InvoiceService;
use App\Models\Invoice;
use Filament\Actions\Action;
use Livewire\Component;

final class MarkAsDeclinedAction
{
    public static function make(): Action
    {
        return Action::make('markAsDeclined')
            ->label(__('labels.mark_as_declined'))
            ->icon('heroicon-m-x-circle')
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription(__('labels.manual_mark_declined_warning'))
            ->modalIcon('heroicon-m-exclamation-triangle')
            ->modalIconColor('danger')
            ->visible(static fn (Invoice $record) => auth()->user()?->can('mark-declined', $record) ?? false)
            ->action(static function (Invoice $record, InvoiceService $invoiceService): void {
                $invoiceService->markAsDeclined(new InvoiceIdList([InvoiceId::create($record->id)]));
            })
            ->successNotificationTitle(__('notifications.invoice_status_updated'))
            ->after(static fn (Component $livewire) => $livewire->dispatch('refresh'));
    }
}
