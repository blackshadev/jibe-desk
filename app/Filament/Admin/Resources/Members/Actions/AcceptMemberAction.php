<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Members\Actions;

use App\Domain\Members\AcceptMemberService;
use App\Domain\Members\MemberId;
use App\Models\Member;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

final class AcceptMemberAction
{
    public static function make(): Action
    {
        return Action::make('acceptMember')
            ->label(__('labels.accept_member'))
            ->icon(Heroicon::CheckCircle)
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription(__('labels.accept_member_description'))
            ->visible(static fn (Member $record): bool => $record->isPending())
            ->action(static function (Member $record, AcceptMemberService $service): void {
                $service->accept(MemberId::create($record->id));
            })
            ->successNotificationTitle(__('notifications.member_accepted'));
    }
}
