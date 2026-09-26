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

    public function test_it_exposes_the_ids_as_values(): void
    {
        $subject = new PurchaseOrderIdList([
            PurchaseOrderId::create(4),
            PurchaseOrderId::create(11),
        ]);

        static::assertSame([4, 11], $subject->values());
    }

    public function test_it_exposes_the_values_of_a_list_created_from_array(): void
    {
        $subject = PurchaseOrderIdList::fromArray([1, 2, 3]);

        static::assertSame([1, 2, 3], $subject->values());
    }

    public function test_it_exposes_no_values_for_an_empty_list(): void
    {
        $subject = new PurchaseOrderIdList([]);

        static::assertSame([], $subject->values());
    }

    public function test_it_rejects_invalid_items(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PurchaseOrderIdList([new stdClass()]);
    }
}
