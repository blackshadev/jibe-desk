<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Registration\Events;

use App\Domain\Members\MemberId;
use App\Domain\Registration\Events\NewMemberRegistration;
use App\Domain\Registration\MembershipData;
use App\Domain\Registration\RegistrationSource;
use Tests\UnitTestCase;

final class NewMemberRegistrationTest extends UnitTestCase
{
    public function test_it_holds_all_properties(): void
    {
        $memberId = MemberId::create(42);
        $memberName = 'Vries, Jan de';
        $memberEmail = 'jan@example.com';
        $membershipData = MembershipData::createDefault();
        $source = RegistrationSource::RegistrationForm;

        $event = new NewMemberRegistration(
            memberId: $memberId,
            memberName: $memberName,
            memberEmail: $memberEmail,
            membershipData: $membershipData,
            source: $source,
        );

        static::assertSame($memberId, $event->memberId);
        static::assertSame($memberName, $event->memberName);
        static::assertSame($memberEmail, $event->memberEmail);
        static::assertSame($membershipData, $event->membershipData);
        static::assertSame($source, $event->source);
    }
}
