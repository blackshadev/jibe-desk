<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\StorageSpaceRentals;

use App\Domain\StorageSpaceRentals\StorageSpaceRentalId;
use Tests\Unit\Domain\NumericIdTestCase;
use Override;

final class StorageSpaceRentalIdTest extends NumericIdTestCase
{
    #[Override]
    protected function getSubject(): string
    {
        return StorageSpaceRentalId::class;
    }
}
