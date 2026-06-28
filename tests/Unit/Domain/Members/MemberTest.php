<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Members;

use App\Domain\Members\HouseholdId;
use App\Domain\Members\Member;
use App\Domain\Members\MemberId;
use App\Domain\Members\MembershipId;
use Tests\UnitTestCase;

final class MemberTest extends UnitTestCase
{
    public function test_it_stores_all_properties(): void
    {
        $subject = new Member(
            id: MemberId::create(42),
            membershipId: MembershipId::create(1),
            isVolunteer: true,
            householdId: HouseholdId::create(5),
            age: 25,
        );

        static::assertSame(42, $subject->id->value);
        static::assertSame(1, $subject->membershipId->value);
        static::assertTrue($subject->isVolunteer);
        static::assertSame(5, $subject->householdId->value);
        static::assertSame(25, $subject->age);
    }

    public function test_is_in_household_returns_true_when_household_id_set(): void
    {
        $subject = new Member(
            id: MemberId::create(1),
            membershipId: MembershipId::create(1),
            isVolunteer: false,
            householdId: HouseholdId::create(5),
            age: 30,
        );

        static::assertTrue($subject->isInHousehold());
    }

    public function test_is_in_household_returns_false_when_no_household(): void
    {
        $subject = new Member(
            id: MemberId::create(1),
            membershipId: MembershipId::create(1),
            isVolunteer: false,
            householdId: null,
            age: 30,
        );

        static::assertFalse($subject->isInHousehold());
    }

    public function test_is_youngster_returns_true_when_under_18(): void
    {
        $subject = new Member(
            id: MemberId::create(1),
            membershipId: MembershipId::create(1),
            isVolunteer: false,
            householdId: null,
            age: 15,
        );

        static::assertTrue($subject->isYoungster());
    }

    public function test_is_youngster_returns_false_when_18_or_older(): void
    {
        $subject = new Member(
            id: MemberId::create(1),
            membershipId: MembershipId::create(1),
            isVolunteer: false,
            householdId: null,
            age: 18,
        );

        static::assertFalse($subject->isYoungster());
    }
}
