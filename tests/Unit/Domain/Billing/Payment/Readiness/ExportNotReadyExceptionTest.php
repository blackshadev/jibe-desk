<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Billing\Payment\Readiness;

use App\Domain\Billing\Payment\Readiness\ExportNotReadyException;
use App\Domain\Billing\Payment\Readiness\ExportReadinessReport;
use App\Domain\Billing\Payment\SepaBatchId;
use Tests\UnitTestCase;

final class ExportNotReadyExceptionTest extends UnitTestCase
{
    public function test_it_carries_the_report(): void
    {
        $report = new ExportReadinessReport(
            batchId: SepaBatchId::create(3),
            issues: [],
            noticePeriodDays: 14,
        );

        $exception = new ExportNotReadyException($report);

        static::assertSame($report, $exception->report);
        static::assertSame('The SEPA batch is not ready for export.', $exception->getMessage());
    }
}
