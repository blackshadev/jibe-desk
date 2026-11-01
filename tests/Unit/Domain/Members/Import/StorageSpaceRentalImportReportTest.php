<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Members\Import;

use App\Domain\Members\Import\StorageSpaceRentalImportReport;
use Tests\UnitTestCase;

final class StorageSpaceRentalImportReportTest extends UnitTestCase
{
    public function test_it_stores_the_rental_and_billing_counts(): void
    {
        $subject = new StorageSpaceRentalImportReport(importedRentals: 72, billingInstancesShifted: 60);

        static::assertSame(72, $subject->importedRentals);
        static::assertSame(60, $subject->billingInstancesShifted);
    }
}
