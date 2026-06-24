<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\InvoiceBatches\Pages;

use App\Domain\Invoices\InvoiceBatchId;
use App\Domain\Invoices\InvoiceBatchService;
use App\Domain\Invoices\InvoiceBatchStatus;
use App\Domain\Invoices\InvoiceStatus;
use App\Domain\Invoices\SepaExportService;
use App\Filament\Admin\Resources\InvoiceBatches\Helpers\OnOpenInvoiceBatch;
use App\Filament\Admin\Resources\InvoiceBatches\InvoiceBatchResource;
use App\Filament\Admin\Resources\InvoiceBatches\RelationManagers\InvoiceBatchInvoicesRelationManager;
use App\Filament\Admin\Resources\InvoiceBatches\Widgets\BatchStatsOverview;
use App\Models\Invoice;
use App\Models\InvoiceBatch;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\EditRecord;
use Livewire\Attributes\On;
use Override;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use ZipArchive;

final class EditInvoiceBatch extends EditRecord
{
    protected static string $resource = InvoiceBatchResource::class;

    #[Override]
    public function getRelationManagers(): array
    {
        return [
            InvoiceBatchInvoicesRelationManager::make(),
        ];
    }

    #[Override]
    protected function getHeaderWidgets(): array
    {
        return [
            BatchStatsOverview::make(['record' => $this->getRecord()]),
        ];
    }

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            Action::make('addInvoices')
                ->label(__('labels.add_invoices'))
                ->icon('heroicon-m-plus')
                ->schema([
                    Select::make('invoice_ids')
                        ->label(__('labels.invoices'))
                        ->multiple()
                        ->searchable()
                        ->options(
                            static fn () => Invoice::query()
                                ->whereNull('invoice_batch_id')
                                ->where('status', InvoiceStatus::Open)
                                ->orderBy('invoice_number')
                                ->get()
                                ->mapWithKeys(static fn (Invoice $invoice) => [
                                    $invoice->id => $invoice->invoice_number . ' — ' . $invoice->recipient_name,
                                ]),
                        ),
                ])
                ->requiresConfirmation()
                ->action(static function (array $data, InvoiceBatch $record): void {
                    Invoice::query()
                        ->whereIn('id', $data['invoice_ids'])
                        ->update(['invoice_batch_id' => $record->id]);
                })
                ->successNotificationTitle(__('notifications.invoices_added_to_batch'))
                ->after(static fn (EditRecord $livewire) => $livewire->dispatch('refreshInvoicesTable'))
                ->visible(OnOpenInvoiceBatch::make(...)),

            Action::make('closeBatch')
                ->label(__('labels.close_batch'))
                ->icon('heroicon-m-lock-closed')
                ->color('warning')
                ->requiresConfirmation()
                ->action(static function (InvoiceBatch $record, InvoiceBatchService $batchService): void {
                    $batchService->closeBatch(InvoiceBatchId::create($record->id));
                })
                ->after(static fn (EditRecord $livewire) => $livewire->dispatch('refreshInvoicesTable'))
                ->successNotificationTitle(__('notifications.batch_closed'))
                ->visible(OnOpenInvoiceBatch::make(...)),

            Action::make('completeBatch')
                ->label(__('labels.complete_batch'))
                ->icon('heroicon-m-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->action(static function (InvoiceBatch $record, InvoiceBatchService $batchService, Action $action): void {
                    try {
                        $batchService->completeBatch(InvoiceBatchId::create($record->id));
                    } catch (Throwable) {
                        $action->failure();
                    }
                })
                ->disabled(static fn (InvoiceBatch $record) => $record->openInvoiceCount > 0)
                ->successNotificationTitle(__('notifications.batch_completed'))
                ->failureNotificationTitle(__('notifications.batch_not_completable'))
                ->after(static fn (EditRecord $livewire) => $livewire->dispatch('refreshInvoicesTable'))
                ->hidden(static fn (InvoiceBatch $record) => $record->status !== InvoiceBatchStatus::Pending),

            Action::make('exportSepa')
                ->label(__('labels.export_sepa'))
                ->icon('heroicon-m-arrow-down-tray')
                ->color('info')
                ->action(static function (InvoiceBatch $record, SepaExportService $exporter): Response {
                    $xml = $exporter->export(InvoiceBatchId::create($record->id));

                    $filename = 'sepa-batch-' . $record->id . '-' . $record->invoice_date->format('Y-m-d');
                    $zip = new ZipArchive();
                    $tmp = tempnam(sys_get_temp_dir(), $filename) . '.zip';
                    $zip->open($tmp, ZipArchive::CREATE);
                    if ($xml->directDebit) {
                        $zip->addFromString('sepa-direct-debits.xml', $xml->directDebit);
                    }
                    if ($xml->creditTransfers) {
                        $zip->addFromString('sepa-credit-transfers.xml', $xml->creditTransfers);
                    }
                    $zip->close();

                    return response()->download(
                        $tmp,
                        $filename . '.zip',
                        ['Content-Type' => 'application/zip'],
                    );
                })
                ->visible(static fn (InvoiceBatch $record) => $record->status === InvoiceBatchStatus::Pending),
        ];
    }

    #[Override]
    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction()->visible(OnOpenInvoiceBatch::make(...)),
            $this->getCancelFormAction()->visible(OnOpenInvoiceBatch::make(...)),
        ];
    }

    #[On('refreshInvoicesTable')]
    public function refresh(): void
    {
    }
}
