<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Members\Dto;

use App\Domain\Members\Dto\NewMemberMembershipInformation;
use App\Domain\Members\MembershipId;
use Tests\UnitTestCase;

final class NewMemberMembershipInformationTest extends UnitTestCase
{
    public function test_constructor_stores_membership_id(): void
    {
        $membershipId = MembershipId::create(42);

        $dto = new NewMemberMembershipInformation(
            membershipId: $membershipId,
        );

        static::assertSame(42, $dto->membershipId->value);
    }
}
