<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Members\Import;

use App\Domain\Members\Import\StorageSpaceRentalImportRequest;
use DateTimeImmutable;
use Tests\UnitTestCase;

final class StorageSpaceRentalImportRequestTest extends UnitTestCase
{
    public function test_it_stores_all_properties(): void
    {
        $billingStart = new DateTimeImmutable('2027-01-01');

        $subject = new StorageSpaceRentalImportRequest(
            path: '/tmp/rentals.csv',
            dryRun: true,
            billingStart: $billingStart,
        );

        static::assertSame('/tmp/rentals.csv', $subject->path);
        static::assertTrue($subject->dryRun);
        static::assertSame($billingStart, $subject->billingStart);
    }
}
