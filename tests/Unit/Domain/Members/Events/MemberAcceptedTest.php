<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Members\Events;

use App\Domain\Members\Events\MemberAccepted;
use App\Domain\Members\MemberId;
use Tests\UnitTestCase;

final class MemberAcceptedTest extends UnitTestCase
{
    public function test_it_holds_the_member_id(): void
    {
        $memberId = MemberId::create(42);

        $event = new MemberAccepted($memberId);

        static::assertSame($memberId, $event->memberId);
    }
}
