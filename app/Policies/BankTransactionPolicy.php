<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\BankTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Override;

final class BankTransactionPolicy extends ResourcePolicy
{
    #[Override]
    protected static function permissionPrefix(): string
    {
        return 'bank_transactions';
    }

    #[Override]
    public function update(User $user, Model $record): bool
    {
        if ($record instanceof BankTransaction && $record->isCompleted()) {
            return false;
        }

        return parent::update($user, $record);
    }

    #[Override]
    public function delete(User $user, Model $record): bool
    {
        if ($record instanceof BankTransaction && $record->isCompleted()) {
            return false;
        }

        return parent::delete($user, $record);
    }
}
