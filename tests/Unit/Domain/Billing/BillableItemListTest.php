<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Billing;

use App\Domain\Billing\BillableItem;
use App\Domain\Billing\BillableItemId;
use App\Domain\Billing\BillableItemList;
use App\Domain\Bookkeeping\CostCenterId;
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
