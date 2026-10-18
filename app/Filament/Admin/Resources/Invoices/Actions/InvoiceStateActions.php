<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Invoices\Actions;

use App\Domain\Billing\Invoices\InvoiceId;
use App\Domain\Billing\Invoices\InvoiceIdList;
use App\Domain\Billing\Invoices\InvoiceService;
use App\Filament\Admin\Resources\Invoices\InvoiceResource;
use App\Models\Invoice;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\RelationManagers\RelationManager;
use Livewire\Component;

final class InvoiceStateActions
{
    public static function make(): array
    {
        return [
            Action::make('markAsPending')
                ->label(__('labels.mark_as_pending'))
                ->icon('heroicon-m-clock')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription(static fn (Invoice $record) => __($record->member_id !== null ? 'labels.manual_mark_pending_warning' : 'labels.manual_mark_pending_check'))
                ->modalIcon(static fn (Invoice $record) => $record->member_id !== null ? 'heroicon-m-exclamation-triangle' : null)
                ->modalIconColor(static fn (Invoice $record) => $record->member_id !== null ? 'danger' : 'primary')
                ->visible(static fn (Invoice $record): bool => auth()->user()?->can('mark-pending', $record) ?? false)
                ->action(static function (Invoice $record, InvoiceService $invoiceService): void {
                    $invoiceService->markAsPending(new InvoiceIdList([InvoiceId::create($record->id)]));
                })
                ->successRedirectUrl(static fn (Component $livewire, Invoice $record): ?string => (
                    $livewire instanceof EditRecord ? InvoiceResource::getUrl('view', ['record' => $record]) : null
                ))
                ->after(static function (Component $livewire): void {
                    $livewire->dispatch('refresh');

                    if ($livewire instanceof RelationManager) {
                        $livewire->dispatch('refreshInvoicesTable');
                    }
                })
                ->successNotificationTitle(__('notifications.invoice_status_updated')),

            Action::make('markAsPaid')
                ->label(__('labels.mark_as_paid'))
                ->icon('heroicon-m-banknotes')
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription(__('labels.manual_mark_paid_warning'))
                ->modalIcon('heroicon-m-exclamation-triangle')
                ->modalIconColor('danger')
                ->visible(static fn (Invoice $record): bool => auth()->user()?->can('mark-paid', $record) ?? false)
                ->action(static function (Invoice $record, InvoiceService $invoiceService): void {
                    $invoiceService->markAsPaid(new InvoiceIdList([InvoiceId::create($record->id)]));
                })
                ->successRedirectUrl(static fn (Component $livewire, Invoice $record): ?string => (
                    $livewire instanceof EditRecord ? InvoiceResource::getUrl('view', ['record' => $record]) : null
                ))
                ->after(static function (Component $livewire): void {
                    $livewire->dispatch('refresh');

                    if ($livewire instanceof RelationManager) {
                        $livewire->dispatch('refreshInvoicesTable');
                    }
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
                ->visible(static fn (Invoice $record): bool => auth()->user()?->can('mark-declined', $record) ?? false)
                ->action(static function (Invoice $record, InvoiceService $invoiceService): void {
                    $invoiceService->markAsDeclined(new InvoiceIdList([InvoiceId::create($record->id)]));
                })
                ->successRedirectUrl(static fn (Component $livewire, Invoice $record): ?string => (
                    $livewire instanceof EditRecord ? InvoiceResource::getUrl('view', ['record' => $record]) : null
                ))
                ->after(static function (Component $livewire): void {
                    $livewire->dispatch('refresh');

                    if ($livewire instanceof RelationManager) {
                        $livewire->dispatch('refreshInvoicesTable');
                    }
                })
                ->successNotificationTitle(__('notifications.invoice_status_updated')),
        ];
    }
}
