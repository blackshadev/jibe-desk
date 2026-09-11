<?php

declare(strict_types=1);

namespace App\Policies;

use Override;

final class BankAccountPolicy extends ResourcePolicy
{
    #[Override]
    protected static function permissionPrefix(): string
    {
        return 'bank_accounts';
    }
}
