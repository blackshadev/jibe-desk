<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Bookkeeping\CostCenters\Import;

use App\Domain\Bookkeeping\CostCenters\Import\CostCenterImportReport;
use Tests\UnitTestCase;

final class CostCenterImportReportTest extends UnitTestCase
{
    public function test_it_stores_all_properties(): void
    {
        $subject = new CostCenterImportReport(
            costCentersInFile: 197,
            groupsInFile: 31,
            costCentersCreated: 190,
            costCentersUpdated: 7,
            groupsCreated: 25,
            groupsUpdated: 6,
            costCentersAbsentFromFiles: ['9999'],
            groupsAbsentFromFiles: ['999'],
        );

        static::assertSame(197, $subject->costCentersInFile);
        static::assertSame(31, $subject->groupsInFile);
        static::assertSame(190, $subject->costCentersCreated);
        static::assertSame(7, $subject->costCentersUpdated);
        static::assertSame(25, $subject->groupsCreated);
        static::assertSame(6, $subject->groupsUpdated);
        static::assertSame(['9999'], $subject->costCentersAbsentFromFiles);
        static::assertSame(['999'], $subject->groupsAbsentFromFiles);
    }
}
