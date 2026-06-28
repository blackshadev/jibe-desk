<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices;

use App\Domain\Invoices\ApplyInvoiceLines;
use App\Domain\Invoices\Billing\BillableItem;
use App\Domain\Invoices\Billing\BillableItemId;
use App\Domain\Invoices\Billing\BillableItemList;
use App\Domain\Invoices\Billing\CostCenterId;
use App\Domain\Invoices\CompoundPrice;
use App\Domain\Members\MemberId;
use Carbon\CarbonImmutable;
use Tests\UnitTestCase;

final class ApplyInvoiceLinesTest extends UnitTestCase
{
    public function test_it_stores_all_properties(): void
    {
        $memberId = MemberId::create(42);
        $date = CarbonImmutable::parse('2026-01-15');
        $items = new BillableItemList([
            new BillableItem(BillableItemId::create(1), new CompoundPrice(10.0, 2.1), 1.0, 'Test', CostCenterId::create(1)),
        ]);

        $subject = new ApplyInvoiceLines(
            memberId: $memberId,
            date: $date,
            items: $items,
        );

        static::assertSame($memberId, $subject->memberId);
        static::assertSame($date, $subject->date);
        static::assertSame($items, $subject->items);
    }
}
