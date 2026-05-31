<?php

declare(strict_types=1);

namespace App\Filament\Admin\Labels;

use App\Domain\Invoices\InvoiceStatus;

final class InvoiceStatusLabels
{
    public static function options(): array
    {
        return [
            InvoiceStatus::Open->value => __('labels.invoice_status.open'),
            InvoiceStatus::Paid->value => __('labels.invoice_status.paid'),
            InvoiceStatus::Pending->value => __('labels.invoice_status.pending'),
        ];
    }
}
