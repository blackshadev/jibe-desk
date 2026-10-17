<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\SepaBatches\Pages;

use App\Domain\Invoices\InvoiceStatus;
use App\Domain\Invoices\SepaBatchId;
use App\Domain\Invoices\SepaBatchService;
use App\Domain\Invoices\SepaBatchStatus;
use App\Domain\Invoices\SepaExportService;
use App\Domain\PurchaseOrders\PurchaseOrderStatus;
use App\Filament\Admin\Resources\SepaBatches\Helpers\OnOpenSepaBatch;
use App\Filament\Admin\Resources\SepaBatches\RelationManagers\SepaBatchInvoicesRelationManager;
use App\Filament\Admin\Resources\SepaBatches\RelationManagers\SepaBatchPurchaseOrdersRelationManager;
use App\Filament\Admin\Resources\SepaBatches\SepaBatchResource;
use App\Filament\Admin\Resources\SepaBatches\Widgets\BatchStatsOverview;
use App\Filament\Admin\Resources\SepaBatches\Widgets\SepaBatchGenerationProgress;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\SepaBatch;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\EditRecord;
use Livewire\Attributes\On;
use Override;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use Webmozart\Assert\Assert;
use ZipArchive;

final class EditSepaBatch extends EditRecord
{
    #[Override]
    protected static string $resource = SepaBatchResource::class;

    #[Override]
    public function getRelationManagers(): array
    {
        return [
            SepaBatchInvoicesRelationManager::make(),
            SepaBatchPurchaseOrdersRelationManager::make(),
        ];
    }

    #[Override]
    protected function getHeaderWidgets(): array
    {
        $record = $this->getRecord();
        Assert::isInstanceOf($record, SepaBatch::class);

        return [
            $record->isGenerating()
                ? SepaBatchGenerationProgress::make(['record' => $record])
                : BatchStatsOverview::make(['record' => $record]),
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
                                ->whereNull('sepa_batch_id')
                                ->where('status', InvoiceStatus::Open)
                                ->orderBy('invoice_number')
                                ->get()
                                ->mapWithKeys(static fn (Invoice $invoice) => [
                                    $invoice->id => $invoice->invoice_number . ' — ' . $invoice->recipient_name,
                                ]),
                        ),
                ])
                ->requiresConfirmation()
                ->action(static function (array $data, SepaBatch $record): void {
                    Invoice::query()
                        ->whereIn('id', $data['invoice_ids'])
                        ->update(['sepa_batch_id' => $record->id]);
                })
                ->successNotificationTitle(__('notifications.invoices_added_to_batch'))
                ->after(static fn (EditRecord $livewire) => $livewire->dispatch('refreshInvoicesTable'))
                ->visible(OnOpenSepaBatch::make(...)),

            Action::make('addPurchaseOrders')
                ->label(__('labels.add_purchase_orders'))
                ->icon('heroicon-m-plus')
                ->schema([
                    Select::make('purchase_order_ids')
                        ->label(__('labels.purchase_orders'))
                        ->multiple()
                        ->searchable()
                        ->options(
                            static fn () => PurchaseOrder::query()
                                ->whereNull('sepa_batch_id')
                                ->where('status', PurchaseOrderStatus::Pending)
                                ->orderBy('date')
                                ->get()
                                ->mapWithKeys(static fn (PurchaseOrder $purchaseOrder) => [
                                    $purchaseOrder->id => sprintf(
                                        '[%s] %s — %s',
                                        $purchaseOrder->date->format('Y-m-d'),
                                        $purchaseOrder->description,
                                        $purchaseOrder->total,
                                    ),
                                ]),
                        ),
                ])
                ->requiresConfirmation()
                ->action(static function (array $data, SepaBatch $record): void {
                    PurchaseOrder::query()
                        ->whereIn('id', $data['purchase_order_ids'])
                        ->update(['sepa_batch_id' => $record->id]);
                })
                ->successNotificationTitle(__('notifications.purchase_orders_added_to_batch'))
                ->after(static fn (EditRecord $livewire) => $livewire->dispatch('refreshInvoicesTable'))
                ->visible(OnOpenSepaBatch::make(...)),

            Action::make('closeBatch')
                ->label(__('labels.close_batch'))
                ->icon('heroicon-m-lock-closed')
                ->color('warning')
                ->requiresConfirmation()
                ->action(static function (SepaBatch $record, SepaBatchService $batchService): void {
                    $batchService->closeBatch(SepaBatchId::create($record->id));
                })
                ->after(static fn (EditRecord $livewire) => $livewire->dispatch('refreshInvoicesTable'))
                ->successNotificationTitle(__('notifications.batch_closed'))
                ->visible(OnOpenSepaBatch::make(...)),

            Action::make('completeBatch')
                ->label(__('labels.complete_batch'))
                ->icon('heroicon-m-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->action(static function (SepaBatch $record, SepaBatchService $batchService, Action $action): void {
                    try {
                        $batchService->completeBatch(SepaBatchId::create($record->id));
                    } catch (Throwable) {
                        $action->failure();
                    }
                })
                ->disabled(static fn (SepaBatch $record) => $record->openInvoiceCount > 0)
                ->successNotificationTitle(__('notifications.batch_completed'))
                ->failureNotificationTitle(__('notifications.batch_not_completable'))
                ->after(static fn (EditRecord $livewire) => $livewire->dispatch('refreshInvoicesTable'))
                ->hidden(static fn (SepaBatch $record) => $record->status !== SepaBatchStatus::Pending),

            Action::make('exportSepa')
                ->label(__('labels.export_sepa'))
                ->icon('heroicon-m-arrow-down-tray')
                ->color('info')
                ->action(static function (SepaBatch $record, SepaExportService $exporter): Response {
                    $xml = $exporter->export(SepaBatchId::create($record->id));

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
                ->visible(static fn (SepaBatch $record) => $record->status === SepaBatchStatus::Pending),
            DeleteAction::make(),
        ];
    }

    #[Override]
    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction()->visible(OnOpenSepaBatch::make(...)),
            $this->getCancelFormAction()->visible(OnOpenSepaBatch::make(...)),
        ];
    }

    #[Override]
    #[On('refreshInvoicesTable')]
    public function refresh(): void
    {
    }
}
