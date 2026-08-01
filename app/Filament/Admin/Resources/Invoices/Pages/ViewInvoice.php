<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Invoices\Pages;

use App\Domain\Invoices\InvoiceId;
use App\Domain\Invoices\InvoiceIdList;
use App\Domain\Invoices\InvoiceService;
use App\Domain\Invoices\InvoiceStatus;
use App\Filament\Admin\Resources\Invoices\InvoiceResource;
use App\Models\Invoice;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Override;

final class ViewInvoice extends ViewRecord
{
    #[Override]
    protected static string $resource = InvoiceResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            Action::make('markAsPaid')
                ->label(__('labels.mark_as_paid'))
                ->icon('heroicon-m-banknotes')
                ->requiresConfirmation()
                ->modalDescription(__('labels.manual_mark_paid_warning'))
                ->modalIcon('heroicon-m-exclamation-triangle')
                ->modalIconColor('danger')
                ->visible(static fn (Invoice $record) => $record->status === InvoiceStatus::Pending)
                ->action(static function (Invoice $record, InvoiceService $invoiceService) {
                    $invoiceService->markAsPaid(new InvoiceIdList([InvoiceId::create($record->id)]));
                })
                ->successNotificationTitle(__('notifications.invoice_status_updated')),
            Action::make('markAsDeclined')
                ->label(__('labels.mark_as_declined'))
                ->icon('heroicon-m-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription(__('labels.manual_mark_declined_warning'))
                ->modalIcon('heroicon-m-exclamation-triangle')
                ->modalIconColor('danger')
                ->visible(static fn (Invoice $record) => $record->status === InvoiceStatus::Pending)
                ->action(static function (Invoice $record, InvoiceService $invoiceService) {
                    $invoiceService->markAsDeclined(new InvoiceIdList([InvoiceId::create($record->id)]));
                })
                ->successNotificationTitle(__('notifications.invoice_status_updated')),
        ];
    }
}
