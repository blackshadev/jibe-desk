<?php

declare(strict_types=1);

namespace App\Filament\Admin\Labels;

final class BillMonthLabels
{
    /** @return array<int, string> */
    public static function options(): array
    {
        $options = [];

        for ($month = 1; $month <= 12; $month++) {
            $options[$month] = __('labels.months.' . $month);
        }

        return $options;
    }
}
