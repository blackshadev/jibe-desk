<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Members;

use App\Domain\Invoices\Billing\BillableItemId;
use App\Domain\Members\Membership;
use App\Domain\Members\MembershipId;
use App\Domain\Members\MembershipList;
use Tests\UnitTestCase;

final class MembershipListTest extends UnitTestCase
{
    public function test_it_converts_memberships_to_billing_ids(): void
    {
        $subject = new MembershipList([
            new Membership(MembershipId::create(1), BillableItemId::create(10), BillableItemId::create(20)),
            new Membership(MembershipId::create(2), BillableItemId::create(30), BillableItemId::create(40)),
        ]);

        static::assertSame([10, 20, 30, 40], $subject->asBillingIdList()->toIntArray());
    }
}
