<?php

declare(strict_types=1);

namespace App\Filament\Admin\Labels;

use App\Domain\Invoices\Billing\BillPeriod;

final class BillPeriodLabels
{
    public static function options(): array
    {
        return [
            BillPeriod::Monthly->value => __('labels.bill_periods.monthly'),
            BillPeriod::Quarterly->value => __('labels.bill_periods.quarterly'),
            BillPeriod::Annually->value => __('labels.bill_periods.annually'),
            BillPeriod::Once->value => __('labels.bill_periods.once'),
        ];
    }
}
