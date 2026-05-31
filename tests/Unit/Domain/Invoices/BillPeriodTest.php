<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices;

use App\Domain\Invoices\Billing\BillPeriod;
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
        self::assertSame($expected, $billPeriod->toBillPeriodInMonths());
    }
}
