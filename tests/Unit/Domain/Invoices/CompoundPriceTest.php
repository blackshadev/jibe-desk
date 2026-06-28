<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices;

use App\Domain\Invoices\CompoundPrice;
use App\Domain\Invoices\Formatters\PriceFormatter;
use Tests\UnitTestCase;

final class CompoundPriceTest extends UnitTestCase
{
    public function test_it_creates_empty_price(): void
    {
        $subject = CompoundPrice::empty();

        static::assertSame(0.0, $subject->price);
        static::assertSame(0.0, $subject->vat);
    }

    public function test_it_creates_price_from_unit_price_and_quantity(): void
    {
        $subject = CompoundPrice::create(10.0, 2);

        static::assertSame(20.0, $subject->price);
        static::assertSame(4.2, $subject->vat);
    }

    public function test_it_adds_two_prices(): void
    {
        $first = new CompoundPrice(10.0, 2.1);
        $second = new CompoundPrice(5.0, 1.05);

        $subject = $first->add($second);

        static::assertSame(15.0, $subject->price);
        static::assertEqualsWithDelta(3.15, $subject->vat, 0.000_001);
    }

    public function test_it_formats_as_string(): void
    {
        $subject = new CompoundPrice(12.34, 2.59);

        static::assertSame(PriceFormatter::format(12.34), (string) $subject);
    }
}
