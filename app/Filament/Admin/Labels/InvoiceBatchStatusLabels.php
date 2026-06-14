<?php

declare(strict_types=1);

namespace App\Filament\Admin\Labels;

use App\Domain\Invoices\InvoiceBatchStatus;

final class InvoiceBatchStatusLabels
{
    public static function options(): array
    {
        return [
            InvoiceBatchStatus::Open->value => __('labels.batch_status.open'),
            InvoiceBatchStatus::Pending->value => __('labels.batch_status.pending'),
            InvoiceBatchStatus::Completed->value => __('labels.batch_status.completed'),
        ];
    }
}
