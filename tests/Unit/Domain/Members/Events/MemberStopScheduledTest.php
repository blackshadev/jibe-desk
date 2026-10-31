<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Members\Events;

use App\Domain\Members\Events\MemberStopScheduled;
use App\Domain\Members\MemberId;
use Carbon\CarbonImmutable;
use Tests\UnitTestCase;

final class MemberStopScheduledTest extends UnitTestCase
{
    public function test_it_holds_all_properties(): void
    {
        $memberId = MemberId::create(42);
        $stoppedAt = CarbonImmutable::parse('2026-12-31 23:59:59');

        $event = new MemberStopScheduled(
            memberId: $memberId,
            memberName: 'Vries, Jan de',
            memberEmail: 'jan@example.com',
            stoppedAt: $stoppedAt,
            isImmediate: false,
        );

        static::assertSame($memberId, $event->memberId);
        static::assertSame('Vries, Jan de', $event->memberName);
        static::assertSame('jan@example.com', $event->memberEmail);
        static::assertSame($stoppedAt, $event->stoppedAt);
        static::assertFalse($event->isImmediate);
    }
}
