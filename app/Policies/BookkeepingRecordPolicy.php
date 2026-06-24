<?php

declare(strict_types=1);

namespace App\Policies;

final class BookkeepingRecordPolicy extends ResourcePolicy
{
    protected static function permissionPrefix(): string
    {
        return 'bookkeeping_records';
    }
}
