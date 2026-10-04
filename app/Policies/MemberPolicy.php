<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Override;

final class MemberPolicy extends ResourcePolicy
{
    #[Override]
    protected static function permissionPrefix(): string
    {
        return 'members';
    }

    #[Override]
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_members') || $user->isMember();
    }

    #[Override]
    public function view(User $user, Model $record): bool
    {
        if ($user->can('view_members')) {
            return true;
        }

        return $user->member !== null && in_array($record->getKey(), $user->visibleMemberIds(), true);
    }

    public function viewPaymentInformation(User $user, Model $record): bool
    {
        return $user->can('view_member_payment_information') || in_array($record->getKey(), $user->visibleMemberIds(), true);
    }

    public function updatePaymentInformation(User $user, Model $record): bool
    {
        return $user->can('update_member_payment_information');
    }

    public function viewAddressInformation(User $user, Model $record): bool
    {
        return $user->can('view_member_address_information') || in_array($record->getKey(), $user->visibleMemberIds(), true);
    }

    public function updateAddressInformation(User $user, Model $record): bool
    {
        return $user->can('update_member_address_information');
    }

    public function viewRegistrationData(User $user, Model $record): bool
    {
        return $user->can('view_member_registration_data') || in_array($record->getKey(), $user->visibleMemberIds(), true);
    }

    public function updateRegistrationData(User $user, Model $record): bool
    {
        return $user->can('update_member_registration_data');
    }
}
