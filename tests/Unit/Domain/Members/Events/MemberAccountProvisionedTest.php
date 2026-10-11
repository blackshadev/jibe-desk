<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Members\Events;

use App\Domain\Members\Events\MemberAccountProvisioned;
use App\Domain\Members\MemberId;
use Tests\UnitTestCase;

final class MemberAccountProvisionedTest extends UnitTestCase
{
    public function test_it_holds_all_properties(): void
    {
        $memberId = MemberId::create(42);

        $event = new MemberAccountProvisioned(
            memberId: $memberId,
            memberName: 'Vries, Jan de',
            memberEmail: 'jan@example.com',
        );

        static::assertSame($memberId, $event->memberId);
        static::assertSame('Vries, Jan de', $event->memberName);
        static::assertSame('jan@example.com', $event->memberEmail);
    }
}
