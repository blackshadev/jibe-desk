<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\PurchaseOrders;

use App\Domain\PurchaseOrders\PurchaseOrderId;
use App\Domain\PurchaseOrders\PurchaseOrderIdList;
use InvalidArgumentException;
use stdClass;
use Tests\UnitTestCase;

final class PurchaseOrderIdListTest extends UnitTestCase
{
    public function test_it_creates_from_array(): void
    {
        $subject = PurchaseOrderIdList::fromArray([1, 2, 3]);

        static::assertSame([1, 2, 3], array_map(static fn (PurchaseOrderId $id): int => $id->value, $subject->ids));
    }

    public function test_it_accepts_empty_array(): void
    {
        $subject = new PurchaseOrderIdList([]);

        static::assertSame([], $subject->ids);
    }

    public function test_it_rejects_invalid_items(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PurchaseOrderIdList([new stdClass()]);
    }
}
