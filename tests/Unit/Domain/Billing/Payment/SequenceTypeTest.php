<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Billing\Payment;

use App\Domain\Billing\Payment\SequenceType;
use Tests\UnitTestCase;

final class SequenceTypeTest extends UnitTestCase
{
    public function test_optionally_from_maps_a_null_to_null(): void
    {
        static::assertNull(SequenceType::optionallyFrom(null));
    }

    public function test_optionally_from_maps_a_stored_value_to_its_case(): void
    {
        static::assertSame(SequenceType::First, SequenceType::optionallyFrom('FRST'));
        static::assertSame(SequenceType::Recurring, SequenceType::optionallyFrom('RCUR'));
    }
}
