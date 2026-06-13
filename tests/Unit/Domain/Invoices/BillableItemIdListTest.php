<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices;

use App\Domain\Invoices\Billing\BillableItemId;
use App\Domain\Invoices\Billing\BillableItemIdList;
use Tests\UnitTestCase;

final class BillableItemIdListTest extends UnitTestCase
{
    public function test_it_converts_to_int_array(): void
    {
        $subject = new BillableItemIdList([
            BillableItemId::create(1),
            BillableItemId::create(2),
        ]);

        static::assertSame([1, 2], $subject->toIntArray());
    }
}
