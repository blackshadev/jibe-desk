<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\SepaBatches\Actions;

use App\Domain\Billing\Payment\SepaBatchId;
use App\Domain\Billing\Payment\SepaBatchService;
use App\Models\SepaBatch;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

final class ReplanSequenceAction
{
    public static function make(): Action
    {
        return Action::make('replanSequence')
            ->label(__('labels.replan_sequence'))
            ->icon('heroicon-m-arrow-path')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading(__('labels.replan_sequence'))
            ->modalDescription(__('labels.replan_sequence_warning'))
            ->visible(
                static fn (SepaBatch $record): bool => auth()->user()?->can('replanSequence', $record) ?? false,
            )
            ->action(static function (SepaBatch $record, SepaBatchService $service): void {
                $service->replanSequenceTypes(SepaBatchId::create($record->id));
            })
            ->successNotificationTitle(__('notifications.sequence_replanned'))
            ->after(static fn (EditRecord $livewire) => $livewire->dispatch('refresh'));
    }
}
