<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Billing\Payment\Readiness;

use App\Domain\Billing\Payment\Readiness\MandateReadiness;
use App\Domain\Billing\Payment\Readiness\MandateReadinessStatus;
use App\Domain\Billing\Payment\SequenceType;
use Tests\UnitTestCase;

final class MandateReadinessTest extends UnitTestCase
{
    public function test_active_mandate_is_usable(): void
    {
        $mandate = new MandateReadiness(status: MandateReadinessStatus::Active);

        static::assertTrue($mandate->isUsable());
    }

    public function test_missing_mandate_is_not_usable(): void
    {
        static::assertFalse(MandateReadiness::missing()->isUsable());
    }

    public function test_expected_sequence_type_defaults_to_recurring(): void
    {
        static::assertSame(SequenceType::Recurring, MandateReadiness::missing()->expectedSequenceType);
    }
}
