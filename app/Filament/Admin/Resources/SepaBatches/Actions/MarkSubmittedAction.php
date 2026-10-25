<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\SepaBatches\Actions;

use App\Domain\Billing\Payment\SepaBatchId;
use App\Domain\Billing\Payment\SepaBatchService;
use App\Domain\Billing\Payment\SepaBatchStatus;
use App\Models\SepaBatch;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

final class MarkSubmittedAction
{
    public static function make(): Action
    {
        return Action::make('markSubmitted')
            ->label(__('labels.mark_submitted'))
            ->icon('heroicon-m-paper-airplane')
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription(__('labels.mark_submitted_warning'))
            ->visible(
                static fn (SepaBatch $record): bool => (
                    $record->status === SepaBatchStatus::Pending
                    && $record->exported_at !== null
                    && $record->submitted_at === null
                    && (auth()->user()?->can('markSubmitted', $record) ?? false)
                ),
            )
            ->action(static function (SepaBatch $record, SepaBatchService $batchService): void {
                $userId = auth()->id();

                if (is_int($userId)) {
                    $batchService->markSubmitted(SepaBatchId::create($record->id), $userId);
                }
            })
            ->after(static fn (EditRecord $livewire) => $livewire->dispatch('refresh'))
            ->successNotificationTitle(__('notifications.batch_submitted'));
    }
}
