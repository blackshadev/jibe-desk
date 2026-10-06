<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Members\Actions;

use App\Domain\Members\MemberId;
use App\Domain\Members\MemberNameFormatter;
use App\Domain\Members\MemberUserRepository;
use App\Domain\Registration\Events\NewMemberRegistration;
use App\Domain\Registration\MembershipData;
use App\Domain\Registration\RegistrationSource;
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
            ->visible(static fn (Member $record): bool => $record->user_id === null)
            ->action(static function (
                Member $record,
                MemberUserRepository $repository,
                Dispatcher $dispatcher,
            ): void {
                $memberId = MemberId::create($record->id);
                $repository->provision($memberId);

                $dispatcher->dispatch(new NewMemberRegistration(
                    memberId: $memberId,
                    memberName: MemberNameFormatter::presentationName(
                        $record->first_name,
                        $record->infix_name,
                        $record->last_name,
                    ),
                    memberEmail: $record->email,
                    membershipData: $record->registration_data ? MembershipData::createFromArray($record->registration_data['membership']) : MembershipData::createDefault(),
                    source: RegistrationSource::AdminPanel,
                ));
            })
            ->successNotificationTitle(__('notifications.user_account_created'));
    }
}
