<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\InvoiceBatches\Helpers;

use App\Domain\Invoices\InvoiceBatchStatus;
use App\Models\InvoiceBatch;

final class OnOpenInvoiceBatch
{
    public static function make(?InvoiceBatch $invoiceBatch): bool
    {
        return $invoiceBatch === null || $invoiceBatch->status === InvoiceBatchStatus::Open;
    }
}
