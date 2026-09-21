<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Members\Actions;

use App\Models\Member;
use Filament\Actions\Action;

final class StopAction
{
    public static function make(): Action
    {
        return Action::make('stop')
            ->label(__('labels.stop_member'))
            ->requiresConfirmation()
            ->visible(static fn (Member $record) => $record->stopped_at === null)
            ->action(static fn (Member $record) => $record->stop())
            ->successNotificationTitle(__('labels.member_stopped'));
    }
}
