<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Billing\BillableItems;

use App\Domain\Billing\BillableItems\BillableItem;
use App\Domain\Billing\BillableItems\BillableItemId;
use App\Domain\Billing\BillableItems\BillableItemList;
use App\Domain\Bookkeeping\CostCenters\CostCenterId;
use App\Domain\SharedKernel\CompoundPrice;
use InvalidArgumentException;
use stdClass;
use Tests\UnitTestCase;

final class BillableItemListTest extends UnitTestCase
{
    public function test_it_stores_items(): void
    {
        $items = [new BillableItem(BillableItemId::create(1), new CompoundPrice(10.0, 2.1), 1.0, 'Test', CostCenterId::create(1))];

        $subject = new BillableItemList($items);

        static::assertSame($items, $subject->items);
    }

    public function test_it_rejects_non_billable_items(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new BillableItemList([new stdClass()]);
    }
}
