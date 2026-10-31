<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Members\Import;

use App\Domain\Members\Gender;
use App\Domain\Members\MemberId;
use App\Domain\Members\MembershipId;
use App\Infrastructure\Members\Import\CreatedMember;
use App\Infrastructure\Members\Import\MappedMemberRow;
use DateTimeImmutable;
use Tests\UnitTestCase;

final class CreatedMemberTest extends UnitTestCase
{
    public function test_it_stores_all_properties(): void
    {
        $row = new MappedMemberRow(
            lidnr: '124277',
            firstName: 'Djoeke',
            infixName: '',
            lastName: 'Adriaanse',
            fullName: 'Djoeke Adriaanse',
            email: 'djoeke@example.com',
            contactEmail: 'djoeke@example.com',
            birthdate: new DateTimeImmutable('2009-02-20'),
            gender: Gender::Female,
            addressStreet: 'Krachtveld',
            addressHousenumber: '30',
            addressHousenumberAddition: null,
            addressPostalcode: '1359 KJ',
            addressCity: 'Almere',
            membershipType: 'Volwassenlid',
            membershipName: 'Windsurfer',
            isVolunteer: false,
            startDate: new DateTimeImmutable('2024-04-24'),
            stopDate: null,
            iban: 'NL75ABNA0510419224',
            bic: 'ABNANL2A',
            accountHolderName: 'Djoeke Adriaanse',
            attendsRtc: false,
            hasContainer: false,
        );

        $subject = new CreatedMember(MemberId::create(12), MembershipId::create(3), $row);

        static::assertSame(12, $subject->memberId->value);
        static::assertSame(3, $subject->membershipId->value);
        static::assertSame($row, $subject->row);
    }
}
