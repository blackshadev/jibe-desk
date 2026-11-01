<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Members\Import;

use App\Domain\Members\Import\StorageSpaceRentalImportReport;
use Tests\UnitTestCase;

final class StorageSpaceRentalImportReportTest extends UnitTestCase
{
    public function test_it_stores_the_rental_count(): void
    {
        $subject = new StorageSpaceRentalImportReport(importedRentals: 72);

        static::assertSame(72, $subject->importedRentals);
    }
}
