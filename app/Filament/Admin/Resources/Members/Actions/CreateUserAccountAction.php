<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Members\Actions;

use App\Domain\Members\Events\MemberAccountProvisioned;
use App\Domain\Members\MemberId;
use App\Domain\Members\MemberNameFormatter;
use App\Domain\Members\MemberUserRepository;
use App\Models\Member;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Events\Dispatcher;

final class CreateUserAccountAction
{
    public static function make(): Action
    {
        return Action::make('createUserAccount')
            ->label(__('labels.create_user_account'))
            ->icon(Heroicon::Key)
            ->requiresConfirmation()
            ->modalDescription(__('labels.create_user_account_description'))
            ->visible(static fn (Member $record): bool => $record->user_id === null && !$record->isPending())
            ->action(static function (
                Member $record,
                MemberUserRepository $repository,
                Dispatcher $dispatcher,
            ): void {
                $memberId = MemberId::create($record->id);
                $repository->provision($memberId);

                $dispatcher->dispatch(new MemberAccountProvisioned(
                    memberId: $memberId,
                    memberName: MemberNameFormatter::presentationName(
                        $record->first_name,
                        $record->infix_name,
                        $record->last_name,
                    ),
                    memberEmail: $record->email,
                ));
            })
            ->successNotificationTitle(__('notifications.user_account_created'));
    }
}
