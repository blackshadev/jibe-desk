<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\SepaBatches\Actions;

use App\Domain\Billing\Payment\Readiness\ExportReadinessService;
use App\Domain\Billing\Payment\SepaBatchId;
use App\Domain\Billing\Payment\SepaBatchStatus;
use App\Models\SepaBatch;
use Filament\Actions\Action;

final class ReadinessAction
{
    public static function make(): Action
    {
        return Action::make('readiness')
            ->label(__('labels.export_readiness.action'))
            ->icon('heroicon-m-shield-check')
            ->color('gray')
            ->visible(static fn (SepaBatch $record): bool => in_array(
                $record->status,
                [SepaBatchStatus::Open, SepaBatchStatus::Pending],
                true,
            ))
            ->modalHeading(__('labels.export_readiness.heading'))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('labels.close'))
            ->schema(static fn (SepaBatch $record, ExportReadinessService $readiness): array => [
                ExportReadinessView::make($readiness->assess(SepaBatchId::create($record->id))),
            ]);
    }
}
