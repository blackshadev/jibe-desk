<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Members;

use App\Domain\Invoices\Billing\BillableItemId;
use App\Domain\Members\Membership;
use App\Domain\Members\MembershipId;
use Tests\UnitTestCase;

final class MembershipTest extends UnitTestCase
{
    public function test_it_stores_all_properties(): void
    {
        $id = MembershipId::create(1);
        $adultBillableItemId = BillableItemId::create(10);
        $kidsBillableItemId = BillableItemId::create(20);

        $subject = new Membership(
            id: $id,
            adultBillableItemId: $adultBillableItemId,
            kidsBillableItemId: $kidsBillableItemId,
        );

        static::assertSame($id, $subject->id);
        static::assertSame($adultBillableItemId, $subject->adultBillableItemId);
        static::assertSame($kidsBillableItemId, $subject->kidsBillableItemId);
    }
}
