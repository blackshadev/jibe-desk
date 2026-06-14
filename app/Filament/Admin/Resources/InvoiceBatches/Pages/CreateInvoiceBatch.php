<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\InvoiceBatches\Pages;

use App\Domain\Invoices\InvoiceBatchService;
use App\Filament\Admin\Resources\InvoiceBatches\InvoiceBatchResource;
use App\Models\InvoiceBatch;
use Carbon\CarbonImmutable;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Override;

final class CreateInvoiceBatch extends CreateRecord
{
    protected static string $resource = InvoiceBatchResource::class;

    #[Override]
    protected function handleRecordCreation(array $data): Model
    {
        $attachInvoices = $data['attach_invoices'] ?? false;
        unset($data['attach_invoices']);

        $batchService = app(InvoiceBatchService::class);

        $batchId = $batchService->createBatch(
            invoiceDate: CarbonImmutable::parse($data['invoice_date']),
        );

        if ($attachInvoices) {
            $batchService->attachBatchMonth($batchId);
        }

        return InvoiceBatch::findOrFail($batchId->value);
    }

    #[Override]
    protected function getCreatedNotificationTitle(): string
    {
        return __('notifications.batch_created');
    }
}
