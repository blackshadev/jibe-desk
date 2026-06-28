<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Invoices\Billing;

use App\Domain\Invoices\Billing\BillableItem;
use App\Domain\Invoices\Billing\BillableItemId;
use App\Domain\Invoices\Billing\CostCenterId;
use App\Domain\Invoices\CompoundPrice;
use Tests\UnitTestCase;

final class BillableItemTest extends UnitTestCase
{
    public function test_it_stores_all_properties(): void
    {
        $id = BillableItemId::create(1);
        $price = new CompoundPrice(25.0, 5.25);
        $costCenterId = CostCenterId::create(3);

        $subject = new BillableItem(
            id: $id,
            price: $price,
            quantity: 2.0,
            description: 'Membership fee',
            costCenterId: $costCenterId,
        );

        static::assertSame($id, $subject->id);
        static::assertSame($price, $subject->price);
        static::assertSame(2.0, $subject->quantity);
        static::assertSame('Membership fee', $subject->description);
        static::assertSame($costCenterId, $subject->costCenterId);
    }
}
