<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\SepaBatches\Pages;

use App\Domain\Billing\Payment\SepaBatchService;
use App\Filament\Admin\Resources\SepaBatches\SepaBatchResource;
use App\Models\SepaBatch;
use Carbon\CarbonImmutable;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Override;

final class CreateSepaBatch extends CreateRecord
{
    #[Override]
    protected static string $resource = SepaBatchResource::class;

    #[Override]
    protected function handleRecordCreation(array $data): Model
    {
        $attachInvoices = $data['attach_invoices'] ?? false;
        $attachPurchaseOrders = $data['attach_purchase_orders'] ?? false;
        unset($data['attach_invoices'], $data['attach_purchase_orders']);

        $batchService = app(SepaBatchService::class);

        $batchId = $batchService->createBatch(
            invoiceDate: CarbonImmutable::parse($data['invoice_date']),
            sepaTransferDate: CarbonImmutable::parse($data['sepa_transfer_date']),
        );

        if ($attachInvoices) {
            $batchService->attachBatchMonthInvoices($batchId);
        }

        if ($attachPurchaseOrders) {
            $batchService->attachBatchMonthPurchaseOrders($batchId);
        }

        return SepaBatch::findOrFail($batchId->value);
    }

    #[Override]
    protected function getCreatedNotificationTitle(): string
    {
        return __('notifications.batch_created');
    }
}
