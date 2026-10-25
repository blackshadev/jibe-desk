<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\SepaBatches\Actions;

use App\Domain\Billing\Invoices\InvoiceId;
use App\Domain\Billing\Invoices\InvoiceIdList;
use App\Domain\Billing\Invoices\InvoiceService;
use App\Domain\Billing\Invoices\InvoiceStatus;
use App\Domain\Billing\Payment\SepaBatchId;
use App\Domain\Billing\Payment\SepaBatchService;
use App\Domain\Billing\Payment\SepaBatchStatus;
use App\Models\SepaBatch;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\EditRecord;

final class RetryDeclined
{
    public static function make(): Action
    {
        return Action::make('retryDeclined')
            ->label(__('labels.retry_declined_payments'))
            ->icon('heroicon-m-arrow-path')
            ->color('warning')
            ->visible(
                static fn (SepaBatch $record): bool => (
                    (auth()->user()?->can('update_invoices') ?? false)
                    && $record->invoices()->where('status', InvoiceStatus::Declined)->exists()
                ),
            )
            ->disabled(
                static fn (): bool => SepaBatch::query()
                    ->where('status', SepaBatchStatus::Open)
                    ->doesntExist(),
            )
            ->tooltip(static fn (): ?string => SepaBatch::query()
                ->where('status', SepaBatchStatus::Open)
                ->doesntExist()
                    ? __('labels.no_open_batch_for_retry')
                    : null)
            ->schema([
                Select::make('target_batch_id')
                    ->label(__('labels.target_batch'))
                    ->options(
                        static fn (SepaBatch $record): array => SepaBatch::query()
                            ->where('status', SepaBatchStatus::Open)
                            ->whereKeyNot($record->id)
                            ->orderBy('invoice_date')
                            ->get()
                            ->mapWithKeys(static fn (SepaBatch $batch): array => [
                                $batch->id => $batch->invoice_date->format('d-m-Y'),
                            ])
                            ->all(),
                    )
                    ->required()
                    ->helperText(__('labels.retry_declined_target_help')),
            ])
            ->requiresConfirmation()
            ->action(static function (
                SepaBatch $record,
                array $data,
                InvoiceService $invoiceService,
                SepaBatchService $batchService,
            ): void {
                $declinedIds = new InvoiceIdList(
                    $record
                        ->invoices()
                        ->where('status', InvoiceStatus::Declined)
                        ->whereNull('credit_invoice_id')
                        ->pluck('id')
                        ->map(InvoiceId::create(...))
                        ->all(),
                );

                $invoiceService->reopen($declinedIds);

                $batchService->attachInvoices(
                    SepaBatchId::create((int) $data['target_batch_id']),
                    $declinedIds,
                );
            })
            ->successNotificationTitle(__('notifications.declined_payments_retried'))
            ->after(static fn (EditRecord $livewire) => $livewire->dispatch('refresh'));
    }
}
