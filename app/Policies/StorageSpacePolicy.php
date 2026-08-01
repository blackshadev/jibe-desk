<?php

declare(strict_types=1);

namespace App\Policies;

use Override;

final class StorageSpacePolicy extends ResourcePolicy
{
    #[Override]
    protected static function permissionPrefix(): string
    {
        return 'storage_spaces';
    }
}
