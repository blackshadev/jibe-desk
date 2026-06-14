<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\InvoiceBatches\Helpers;

use App\Domain\Invoices\InvoiceBatchStatus;
use App\Domain\Invoices\InvoiceStatus;
use App\Models\Invoice;
use App\Models\InvoiceBatch;
use Filament\Resources\RelationManagers\RelationManager;

final class OnPendingInvoice
{
    public static function make(Invoice $record, RelationManager $livewire): bool
    {
        /** @var InvoiceBatch $batch */
        $batch = $livewire->getOwnerRecord();
        return $record->status === InvoiceStatus::Pending && $batch->status === InvoiceBatchStatus::Pending;
    }
}
