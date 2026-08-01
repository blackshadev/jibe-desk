<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Override;

final class OutgoingEmailPolicy extends ResourcePolicy
{
    #[Override]
    protected static function permissionPrefix(): string
    {
        return 'outgoing_emails';
    }

    #[Override]
    public function create(User $_user): bool
    {
        return false;
    }

    #[Override]
    public function update(User $_user, Model $_record): bool
    {
        return false;
    }

    #[Override]
    public function delete(User $_user, Model $_record): bool
    {
        return false;
    }
}
