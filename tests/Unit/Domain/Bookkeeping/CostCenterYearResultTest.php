<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Bookkeeping;

use App\Domain\Bookkeeping\CostCenterYearResult;
use App\Domain\Invoices\Billing\CostCenterId;
use App\Domain\Invoices\CompoundPrice;
use Tests\UnitTestCase;

final class CostCenterYearResultTest extends UnitTestCase
{
    public function test_it_stores_all_properties(): void
    {
        $costCenterId = CostCenterId::create(1);
        $totalBookkeeping = new CompoundPrice(100.0, 21.0);

        $subject = new CostCenterYearResult(
            costCenterId: $costCenterId,
            number: 'CC-001',
            title: 'Test Cost Center',
            startingAmount: 50.0,
            totalBookkeeping: $totalBookkeeping,
        );

        static::assertSame($costCenterId, $subject->costCenterId);
        static::assertSame('CC-001', $subject->number);
        static::assertSame('Test Cost Center', $subject->title);
        static::assertSame(50.0, $subject->startingAmount);
        static::assertSame($totalBookkeeping, $subject->totalBookkeeping);
    }

    public function test_result_adds_starting_amount_to_total_bookkeeping(): void
    {
        $totalBookkeeping = new CompoundPrice(100.0, 21.0);

        $subject = new CostCenterYearResult(
            costCenterId: CostCenterId::create(1),
            number: 'CC-001',
            title: 'Test',
            startingAmount: 50.0,
            totalBookkeeping: $totalBookkeeping,
        );

        $result = $subject->result();

        static::assertSame(150.0, $result->price);
        static::assertSame(21.0, $result->vat);
    }
}
