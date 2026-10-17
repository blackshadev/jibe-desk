<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\SepaBatches\Helpers;

use App\Domain\Invoices\InvoiceStatus;
use App\Domain\Invoices\SepaBatchStatus;
use App\Models\Invoice;
use App\Models\SepaBatch;
use Filament\Resources\RelationManagers\RelationManager;

final class OnPendingInvoice
{
    public static function make(Invoice $record, RelationManager $livewire): bool
    {
        /** @var SepaBatch $batch */
        $batch = $livewire->getOwnerRecord();
        return $record->status === InvoiceStatus::Pending && $batch->status === SepaBatchStatus::Pending;
    }
}
