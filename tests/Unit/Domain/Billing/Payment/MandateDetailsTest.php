<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Billing\Payment;

use App\Domain\Billing\Payment\MandateDetails;
use Carbon\CarbonImmutable;
use Tests\UnitTestCase;

final class MandateDetailsTest extends UnitTestCase
{
    public function test_it_stores_all_properties(): void
    {
        $signedAt = CarbonImmutable::parse('2026-01-15');

        $subject = new MandateDetails(
            iban: 'NL91ABNA0417164300',
            bic: 'ABNANL2A',
            accountHolderName: 'Jan de Vries',
            signedAt: $signedAt,
        );

        static::assertSame('NL91ABNA0417164300', $subject->iban);
        static::assertSame('ABNANL2A', $subject->bic);
        static::assertSame('Jan de Vries', $subject->accountHolderName);
        static::assertSame($signedAt, $subject->signedAt);
    }
}
