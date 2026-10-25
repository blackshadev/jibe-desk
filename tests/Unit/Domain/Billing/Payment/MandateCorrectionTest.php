<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Billing\Payment;

use App\Domain\Billing\Payment\MandateCorrection;
use Tests\UnitTestCase;

final class MandateCorrectionTest extends UnitTestCase
{
    public function test_it_stores_the_bic_and_account_holder_name(): void
    {
        $subject = new MandateCorrection(
            bic: 'ABNANL2A',
            accountHolderName: 'Jan de Vries',
        );

        static::assertSame('ABNANL2A', $subject->bic);
        static::assertSame('Jan de Vries', $subject->accountHolderName);
    }
}
