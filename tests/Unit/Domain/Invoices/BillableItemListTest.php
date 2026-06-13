<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices;

use App\Domain\Invoices\Billing\BillableItem;
use App\Domain\Invoices\Billing\BillableItemId;
use App\Domain\Invoices\Billing\BillableItemList;
use App\Domain\Invoices\CompoundPrice;
use InvalidArgumentException;
use stdClass;
use Tests\UnitTestCase;

final class BillableItemListTest extends UnitTestCase
{
    public function test_it_stores_items(): void
    {
        $items = [new BillableItem(BillableItemId::create(1), new CompoundPrice(10.0, 2.1), 1.0, 'Test')];

        $subject = new BillableItemList($items);

        static::assertSame($items, $subject->items);
    }

    public function test_it_rejects_non_billable_items(): void
    {
        $this->expectException(InvalidArgumentException::class);

        /** @phpstan-ignore-next-line argument.type */
        new BillableItemList([new stdClass()]);
    }
}
