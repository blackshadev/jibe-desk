<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Billing\Payment\Readiness;

use App\Domain\Billing\Payment\Readiness\SepaBatchExportContext;
use App\Domain\Billing\Payment\SepaBatchStatus;
use Carbon\CarbonImmutable;
use Tests\UnitTestCase;

final class SepaBatchExportContextTest extends UnitTestCase
{
    public function test_it_keeps_the_status_transfer_date_and_close_moment(): void
    {
        $transferDate = CarbonImmutable::parse('2026-11-05');
        $closedAt = CarbonImmutable::parse('2026-10-22 15:30:00');

        $context = new SepaBatchExportContext(
            status: SepaBatchStatus::Pending,
            sepaTransferDate: $transferDate,
            closedAt: $closedAt,
        );

        static::assertSame(SepaBatchStatus::Pending, $context->status);
        static::assertSame($transferDate, $context->sepaTransferDate);
        static::assertSame($closedAt, $context->closedAt);
    }
}
