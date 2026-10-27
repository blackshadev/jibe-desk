<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Billing\Payment;

use App\Domain\Billing\Payment\MandateSequenceResolver;
use App\Domain\Billing\Payment\SequenceType;
use Carbon\CarbonImmutable;
use Tests\UnitTestCase;

final class MandateSequenceResolverTest extends UnitTestCase
{
    public function test_resolve_returns_first_for_a_mandate_that_was_never_collected(): void
    {
        $resolver = new MandateSequenceResolver();

        static::assertSame(SequenceType::First, $resolver->resolve(null));
    }

    public function test_resolve_returns_recurring_for_a_mandate_with_a_last_collection(): void
    {
        $resolver = new MandateSequenceResolver();

        static::assertSame(
            SequenceType::Recurring,
            $resolver->resolve(CarbonImmutable::parse('2026-09-30 00:00:00')),
        );
    }

    public function test_resolve_prefers_a_frozen_first_over_a_stamped_mandate(): void
    {
        $resolver = new MandateSequenceResolver();

        static::assertSame(
            SequenceType::First,
            $resolver->resolve(
                lastCollectedAt: CarbonImmutable::parse('2026-10-23 09:30:00'),
                frozenSequence: SequenceType::First,
            ),
        );
    }

    public function test_resolve_prefers_a_frozen_recurring_over_a_never_collected_mandate(): void
    {
        $resolver = new MandateSequenceResolver();

        static::assertSame(
            SequenceType::Recurring,
            $resolver->resolve(lastCollectedAt: null, frozenSequence: SequenceType::Recurring),
        );
    }

    public function test_resolve_does_not_look_at_the_value_of_the_last_collection(): void
    {
        $resolver = new MandateSequenceResolver();

        static::assertSame(
            SequenceType::Recurring,
            $resolver->resolve(CarbonImmutable::parse('2000-01-01 00:00:00')),
        );
    }
}
