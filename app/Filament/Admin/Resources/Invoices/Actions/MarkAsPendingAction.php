<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Invoices\Actions;

use App\Domain\Invoices\InvoiceId;
use App\Domain\Invoices\InvoiceIdList;
use App\Domain\Invoices\InvoiceService;
use App\Models\Invoice;
use Filament\Actions\Action;
use Livewire\Component;

final class MarkAsPendingAction
{
    public static function make(): Action
    {
        return Action::make('markAsPending')
            ->label(__('labels.mark_as_pending'))
            ->icon('heroicon-m-clock')
            ->color('gray')
            ->requiresConfirmation()
            ->modalDescription(static fn (Invoice $record) => __($record->member_id !== null ? 'labels.manual_mark_pending_warning' : 'labels.manual_mark_pending_check'))
            ->modalIcon(static fn (Invoice $record) => $record->member_id !== null ? 'heroicon-m-exclamation-triangle' : null)
            ->modalIconColor(static fn (Invoice $record) => $record->member_id !== null ? 'danger' : 'primary')
            ->visible(static fn (Invoice $record) => auth()->user()?->can('mark-pending', $record) ?? false)
            ->action(static function (Invoice $record, InvoiceService $invoiceService): void {
                $invoiceService->markAsPending(new InvoiceIdList([InvoiceId::create($record->id)]));
            })
            ->successNotificationTitle(__('notifications.invoice_status_updated'))
            ->after(static fn (Component $livewire) => $livewire->dispatch('refresh'));
    }
}
