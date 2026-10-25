<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\SepaBatches\Actions;

use App\Domain\Billing\Payment\SepaBatchId;
use App\Domain\Billing\Payment\SepaBatchService;
use App\Domain\Billing\Payment\SepaBatchStatus;
use App\Models\SepaBatch;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Throwable;

final class CompleteBatchAction
{
    public static function make(): Action
    {
        return Action::make('completeBatch')
            ->label(__('labels.complete_batch'))
            ->icon('heroicon-m-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->action(static function (SepaBatch $record, SepaBatchService $batchService, Action $action): void {
                try {
                    $batchService->completeBatch(SepaBatchId::create($record->id));
                } catch (Throwable) {
                    $action->failure();
                }
            })
            ->disabled(static fn (SepaBatch $record) => $record->openInvoiceCount > 0)
            ->successNotificationTitle(__('notifications.batch_completed'))
            ->failureNotificationTitle(__('notifications.batch_not_completable'))
            ->after(static fn (EditRecord $livewire) => $livewire->dispatch('refresh'))
            ->hidden(static fn (SepaBatch $record) => $record->status !== SepaBatchStatus::Pending);
    }
}
