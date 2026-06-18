<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Invoices;

use App\Infrastructure\Invoices\SepaConfiguration;
use Tests\UnitTestCase;

final class SepaConfigurationTest extends UnitTestCase
{
    public function test_values_are_stored_correctly(): void
    {
        $config = new SepaConfiguration(
            creditorId: 'NL12ZZZ1234567890',
            creditorName: 'Test Club',
            creditorIban: 'NL91ABNA0417164300',
            creditorBic: 'ABNANL2A',
            painFormat: 'pain.008.001.02',
        );

        static::assertSame('NL12ZZZ1234567890', $config->creditorId);
        static::assertSame('Test Club', $config->creditorName);
        static::assertSame('NL91ABNA0417164300', $config->creditorIban);
        static::assertSame('ABNANL2A', $config->creditorBic);
        static::assertSame('pain.008.001.02', $config->painFormat);
    }
}
