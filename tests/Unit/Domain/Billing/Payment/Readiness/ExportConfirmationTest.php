<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Billing\Payment\Readiness;

use App\Domain\Billing\Invoices\InvoiceId;
use App\Domain\Billing\Payment\Readiness\ExportConfirmation;
use App\Domain\Billing\Payment\Readiness\ExportOverride;
use App\Domain\Billing\Payment\Readiness\ExportReadinessReason;
use Tests\UnitTestCase;

final class ExportConfirmationTest extends UnitTestCase
{
    public function test_override_for_finds_the_matching_invoice(): void
    {
        $override = new ExportOverride(
            invoiceId: InvoiceId::create(5),
            reason: ExportReadinessReason::ExpiredMandate,
            note: 'Member confirmed by phone.',
        );

        $confirmation = new ExportConfirmation(overrides: [$override]);

        static::assertSame($override, $confirmation->overrideFor(InvoiceId::create(5)));
    }

    public function test_override_for_returns_null_when_absent(): void
    {
        $confirmation = new ExportConfirmation(
            overrides: [
                new ExportOverride(InvoiceId::create(5), ExportReadinessReason::ExpiredMandate, 'Note'),
            ],
        );

        static::assertNull($confirmation->overrideFor(InvoiceId::create(6)));
    }
}
