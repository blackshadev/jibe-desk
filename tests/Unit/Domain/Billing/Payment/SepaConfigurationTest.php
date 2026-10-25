<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Billing\Payment;

use App\Domain\Billing\Payment\SepaConfiguration;
use Tests\FeatureTestCase;

final class SepaConfigurationTest extends FeatureTestCase
{
    public function test_values_are_stored_correctly(): void
    {
        $config = new SepaConfiguration(
            creditorId: 'NL12ZZZ1234567890',
            creditorName: 'Test Club',
            creditorIban: 'NL91ABNA0417164300',
            creditorBic: 'ABNANL2A',
            noticePeriodDays: 14,
        );

        static::assertSame('NL12ZZZ1234567890', $config->creditorId);
        static::assertSame('Test Club', $config->creditorName);
        static::assertSame('NL91ABNA0417164300', $config->creditorIban);
        static::assertSame('ABNANL2A', $config->creditorBic);
        static::assertSame(14, $config->noticePeriodDays);
    }

    public function test_notice_period_defaults_to_fourteen_days(): void
    {
        config(['sepa.notice_period_days' => 14]);

        $config = $this->app->make(SepaConfiguration::class);

        static::assertSame(14, $config->noticePeriodDays);
    }
}
