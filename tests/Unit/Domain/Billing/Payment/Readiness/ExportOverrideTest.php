<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Billing\Payment\Readiness;

use App\Domain\Billing\Invoices\InvoiceId;
use App\Domain\Billing\Payment\Readiness\ExportOverride;
use App\Domain\Billing\Payment\Readiness\ExportReadinessReason;
use Tests\UnitTestCase;

final class ExportOverrideTest extends UnitTestCase
{
    public function test_it_keeps_the_reason_and_note(): void
    {
        $override = new ExportOverride(
            invoiceId: InvoiceId::create(9),
            reason: ExportReadinessReason::ExpiredMandate,
            note: 'Collected after written confirmation.',
        );

        static::assertSame(9, $override->invoiceId->value);
        static::assertSame(ExportReadinessReason::ExpiredMandate, $override->reason);
        static::assertSame('Collected after written confirmation.', $override->note);
    }
}
