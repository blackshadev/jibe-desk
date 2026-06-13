<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Members\Events;

use App\Domain\Members\MemberId;
use App\Domain\Members\Events\NewMemberRegistration;
use App\Domain\Registration\MembershipData;
use Tests\UnitTestCase;

final class NewMemberRegistrationTest extends UnitTestCase
{
    public function test_it_holds_all_properties(): void
    {
        $memberId = MemberId::create(42);
        $memberName = 'Vries, Jan de';
        $memberEmail = 'jan@example.com';
        $membershipData = MembershipData::createDefault();

        $event = new NewMemberRegistration(
            memberId: $memberId,
            memberName: $memberName,
            memberEmail: $memberEmail,
            membershipData: $membershipData,
        );

        self::assertSame($memberId, $event->memberId);
        self::assertSame($memberName, $event->memberName);
        self::assertSame($memberEmail, $event->memberEmail);
        self::assertSame($membershipData, $event->membershipData);
    }
}
