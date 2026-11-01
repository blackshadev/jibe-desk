<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Members\Import;

use App\Domain\Members\Import\StorageSpaceRentalImportRequest;
use Tests\UnitTestCase;

final class StorageSpaceRentalImportRequestTest extends UnitTestCase
{
    public function test_it_stores_all_properties(): void
    {
        $subject = new StorageSpaceRentalImportRequest(
            path: '/tmp/rentals.csv',
            dryRun: true,
        );

        static::assertSame('/tmp/rentals.csv', $subject->path);
        static::assertTrue($subject->dryRun);
    }
}
