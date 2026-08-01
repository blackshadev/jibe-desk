<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Override;

final class MemberPolicy extends ResourcePolicy
{
    #[Override]
    protected static function permissionPrefix(): string
    {
        return 'members';
    }

    public function viewPaymentInformation(User $user): bool
    {
        return $user->can('view_member_payment_information');
    }

    public function updatePaymentInformation(User $user): bool
    {
        return $user->can('update_member_payment_information');
    }

    public function viewAddressInformation(User $user): bool
    {
        return $user->can('view_member_address_information');
    }

    public function updateAddressInformation(User $user): bool
    {
        return $user->can('update_member_address_information');
    }

    public function viewRegistrationData(User $user): bool
    {
        return $user->can('view_member_registration_data');
    }

    public function updateRegistrationData(User $user): bool
    {
        return $user->can('update_member_registration_data');
    }
}
