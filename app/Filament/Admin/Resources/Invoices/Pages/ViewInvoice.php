<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Invoices\Pages;

use App\Domain\Invoices\InvoiceId;
use App\Domain\Invoices\InvoiceIdList;
use App\Domain\Invoices\InvoiceService;
use App\Filament\Admin\Resources\Invoices\InvoiceResource;
use App\Models\Invoice;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Livewire\Attributes\On;
use Override;

final class ViewInvoice extends ViewRecord
{
    #[Override]
    protected static string $resource = InvoiceResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            Action::make('markAsPending')
                ->label(__('labels.mark_as_pending'))
                ->icon('heroicon-m-clock')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription(__('labels.manual_mark_pending_warning'))
                ->modalIcon('heroicon-m-exclamation-triangle')
                ->modalIconColor('danger')
                ->visible(static fn (Invoice $record) => auth()->user()?->can('mark-pending', $record) ?? false)
                ->action(static function (Invoice $record, InvoiceService $invoiceService): void {
                    $invoiceService->markAsPending(new InvoiceIdList([InvoiceId::create($record->id)]));
                })
                ->successNotificationTitle(__('notifications.invoice_status_updated'))
                ->after(static fn (ViewInvoice $livewire) => $livewire->dispatch('refresh')),
            Action::make('markAsPaid')
                ->label(__('labels.mark_as_paid'))
                ->icon('heroicon-m-banknotes')
                ->requiresConfirmation()
                ->modalDescription(__('labels.manual_mark_paid_warning'))
                ->modalIcon('heroicon-m-exclamation-triangle')
                ->modalIconColor('danger')
                ->visible(static fn (Invoice $record) => auth()->user()?->can('mark-paid', $record) ?? false)
                ->action(static function (Invoice $record, InvoiceService $invoiceService) {
                    $invoiceService->markAsPaid(new InvoiceIdList([InvoiceId::create($record->id)]));
                })
                ->successNotificationTitle(__('notifications.invoice_status_updated'))
                ->after(static fn (ViewInvoice $livewire) => $livewire->dispatch('refresh')),
            Action::make('markAsDeclined')
                ->label(__('labels.mark_as_declined'))
                ->icon('heroicon-m-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription(__('labels.manual_mark_declined_warning'))
                ->modalIcon('heroicon-m-exclamation-triangle')
                ->modalIconColor('danger')
                ->visible(static fn (Invoice $record) => auth()->user()?->can('mark-declined', $record) ?? false)
                ->action(static function (Invoice $record, InvoiceService $invoiceService) {
                    $invoiceService->markAsDeclined(new InvoiceIdList([InvoiceId::create($record->id)]));
                })
                ->successNotificationTitle(__('notifications.invoice_status_updated'))
                ->after(static fn (ViewInvoice $livewire) => $livewire->dispatch('refresh')),
            Action::make('createCredit')
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
                ->after(static fn (ViewInvoice $livewire) => $livewire->dispatch('refresh')),
        ];
    }

    #[Override]
    #[On('refresh')]
    public function refresh(): void
    {
        $this->record->refresh();
        $this->fillForm();
    }
}
