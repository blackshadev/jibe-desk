<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

final class OutgoingEmailPolicy extends ResourcePolicy
{
    protected static function permissionPrefix(): string
    {
        return 'outgoing_emails';
    }

    public function create(User $_user): bool
    {
        return false;
    }

    public function update(User $_user, Model $_record): bool
    {
        return false;
    }

    public function delete(User $_user, Model $_record): bool
    {
        return false;
    }
}
