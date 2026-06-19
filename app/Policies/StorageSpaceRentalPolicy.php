<?php

declare(strict_types=1);

namespace App\Policies;

final class StorageSpaceRentalPolicy extends ResourcePolicy
{
    protected static function permissionPrefix(): string
    {
        return 'storage_space_rentals';
    }
}
