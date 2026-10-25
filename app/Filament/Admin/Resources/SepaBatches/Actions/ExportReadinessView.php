<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\SepaBatches\Actions;

use App\Domain\Billing\Payment\Readiness\ExportReadinessReport;
use Filament\Schemas\Components\View;

final class ExportReadinessView
{
    public static function make(ExportReadinessReport $report): View
    {
        return View::make('filament.admin.resources.sepa-batches.export-readiness')
            ->viewData(['report' => $report]);
    }

    public static function optionallyMake(ExportReadinessReport $report): ?View
    {
        if ($report->isEmpty()) {
            return null;
        }

        return self::make($report);
    }
}
