<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\BankingTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Override;

final class BankingTransactionPolicy extends ResourcePolicy
{
    protected static function permissionPrefix(): string
    {
        return 'banking_transactions';
    }

    #[Override]
    public function update(User $user, Model $record): bool
    {
        if ($record instanceof BankingTransaction && $record->isCompleted()) {
            return false;
        }

        return parent::update($user, $record);
    }

    #[Override]
    public function delete(User $user, Model $record): bool
    {
        if ($record instanceof BankingTransaction && $record->isCompleted()) {
            return false;
        }

        return parent::delete($user, $record);
    }
}
