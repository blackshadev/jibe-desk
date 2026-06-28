<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices\Formatters;

use App\Domain\Invoices\CompoundPrice;
use App\Domain\Invoices\Formatters\PriceFormatter;
use Tests\UnitTestCase;

final class PriceFormatterTest extends UnitTestCase
{
    public function test_format_adds_euro_sign_and_two_decimals(): void
    {
        static::assertSame('€ 25,50', PriceFormatter::format(25.5));
    }

    public function test_format_zero(): void
    {
        static::assertSame('€ 0,00', PriceFormatter::format(0.0));
    }

    public function test_format_with_thousands_separator(): void
    {
        static::assertSame('€ 1.250,00', PriceFormatter::format(1250.0));
    }

    public function test_format_signless(): void
    {
        static::assertSame('25,50', PriceFormatter::formatSignless(25.5));
    }

    public function test_format_signless_with_null_returns_empty(): void
    {
        static::assertSame('', PriceFormatter::formatSignless(null));
    }

    public function test_format_signless_with_zero_returns_empty(): void
    {
        static::assertSame('', PriceFormatter::formatSignless(0.0));
    }

    public function test_format_compound(): void
    {
        $price = new CompoundPrice(25.5, 5.355);

        static::assertSame('€ 25,50', PriceFormatter::formatCompound($price));
    }

    public function test_format_compound_signless(): void
    {
        $price = new CompoundPrice(25.5, 5.355);

        static::assertSame('25,50', PriceFormatter::formatCompoundSignless($price));
    }

    public function test_format_compound_signless_with_null_returns_empty(): void
    {
        static::assertSame('', PriceFormatter::formatCompoundSignless(null));
    }

    public function test_parse_removes_euro_sign_and_spaces(): void
    {
        static::assertSame(25.5, PriceFormatter::parse('€ 25,50'));
    }

    public function test_parse_without_euro_sign(): void
    {
        static::assertSame(25.5, PriceFormatter::parse('25,50'));
    }

    public function test_parse_with_thousands_separator(): void
    {
        static::assertSame(1250.0, PriceFormatter::parse('€ 1.250,00'));
    }
}
