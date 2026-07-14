<?php

declare(strict_types=1);

namespace App\Policies;

final class BankingTransactionPolicy extends ResourcePolicy
{
    protected static function permissionPrefix(): string
    {
        return 'banking_transactions';
    }
}
