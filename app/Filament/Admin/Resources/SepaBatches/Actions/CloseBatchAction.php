<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\SepaBatches\Actions;

use App\Domain\Billing\Payment\Readiness\ExportReadinessService;
use App\Domain\Billing\Payment\SepaBatchId;
use App\Domain\Billing\Payment\SepaBatchService;
use App\Filament\Admin\Resources\SepaBatches\Helpers\OnOpenSepaBatch;
use App\Models\SepaBatch;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

final class CloseBatchAction
{
    public static function make(): Action
    {
        return Action::make('closeBatch')
            ->label(__('labels.close_batch'))
            ->icon('heroicon-m-lock-closed')
            ->color('warning')
            ->modalHeading(__('labels.close_batch'))
            ->modalDescription(static fn (SepaBatch $record, ExportReadinessService $readiness): string => $readiness->assess(SepaBatchId::create($record->id))->isEmpty()
                ? __('labels.export_readiness.close_confirm')
                : __('labels.export_readiness.close_warning'))
            ->modalSubmitActionLabel(__('labels.close_batch'))
            ->schema(static fn (SepaBatch $record, ExportReadinessService $readiness): array => [
                ExportReadinessView::optionallyMake($readiness->assess(SepaBatchId::create($record->id))),
            ])
            ->action(static function (SepaBatch $record, SepaBatchService $batchService): void {
                $batchService->closeBatch(SepaBatchId::create($record->id));
            })
            ->after(static fn (EditRecord $livewire) => $livewire->dispatch('refresh'))
            ->successNotificationTitle(__('notifications.batch_closed'))
            ->visible(OnOpenSepaBatch::make(...));
    }
}
