<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices;

use App\Domain\Invoices\Billing\BillPeriod;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\UnitTestCase;

final class BillPeriodTest extends UnitTestCase
{
    /** @return iterable<string, array{BillPeriod, int}> */
    public static function billPeriodProvider(): iterable
    {
        yield 'once' => [BillPeriod::Once, PHP_INT_MAX];
        yield 'monthly' => [BillPeriod::Monthly, 1];
        yield 'quarterly' => [BillPeriod::Quarterly, 3];
        yield 'annually' => [BillPeriod::Annually, 12];
    }

    #[DataProvider('billPeriodProvider')]
    public function test_it_converts_to_months(BillPeriod $billPeriod, int $expected): void
    {
        static::assertSame($expected, $billPeriod->toBillPeriodInMonths());
    }

    /** @return iterable<string, array{BillPeriod, string}> */
    public static function periodNameProvider(): iterable
    {
        yield 'monthly' => [BillPeriod::Monthly, 'month'];
        yield 'quarterly' => [BillPeriod::Quarterly, 'quarter'];
        yield 'annually' => [BillPeriod::Annually, 'year'];
    }

    #[DataProvider('periodNameProvider')]
    public function test_it_converts_to_period_name(BillPeriod $billPeriod, string $expected): void
    {
        static::assertSame($expected, $billPeriod->toPeriodName());
    }

    public function test_it_throws_for_once_period_name(): void
    {
        $this->expectException(InvalidArgumentException::class);

        BillPeriod::Once->toPeriodName();
    }
}
