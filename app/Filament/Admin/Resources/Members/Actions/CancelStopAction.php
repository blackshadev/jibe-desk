<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Members\Actions;

use App\Domain\Members\MemberId;
use App\Domain\Members\MemberStopService;
use App\Models\Member;
use Filament\Actions\Action;
use Livewire\Component;

final class CancelStopAction
{
    public static function make(): Action
    {
        return Action::make('cancelStop')
            ->label(__('labels.cancel_member_stop'))
            ->requiresConfirmation()
            ->modalDescription(__('labels.cancel_member_stop_description'))
            ->visible(static fn (Member $record): bool => $record->isScheduledForStopping() && (auth()->user()?->can('scheduleStop', $record) ?? false))
            ->action(static function (Member $record, MemberStopService $service): void {
                $service->cancel(MemberId::create($record->id));
                $record->refresh();
            })
            ->after(static fn (Component $livewire) => $livewire->dispatch('refresh'))
            ->successNotificationTitle(__('notifications.member_stop_cancelled'));
    }
}
