<?php

declare(strict_types=1);

namespace App\Filament\Admin\Labels;

use App\Domain\Billing\Payment\SepaBatchStatus;

final class SepaBatchStatusLabels
{
    public static function options(): array
    {
        return [
            SepaBatchStatus::Open->value => __('labels.batch_status.open'),
            SepaBatchStatus::Pending->value => __('labels.batch_status.pending'),
            SepaBatchStatus::Completed->value => __('labels.batch_status.completed'),
        ];
    }
}
