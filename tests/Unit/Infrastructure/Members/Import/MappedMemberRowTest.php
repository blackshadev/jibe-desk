<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Members\Import;

use App\Domain\Members\Gender;
use App\Infrastructure\Members\Import\MappedMemberRow;
use DateTimeImmutable;
use Tests\UnitTestCase;

final class MappedMemberRowTest extends UnitTestCase
{
    public function test_is_leaver_returns_false_without_a_stop_date(): void
    {
        static::assertFalse($this->row()->isLeaver());
    }

    public function test_is_leaver_returns_true_with_a_stop_date(): void
    {
        static::assertTrue($this->row(stopDate: new DateTimeImmutable('2026-12-31'))->isLeaver());
    }

    public function test_is_sail_instructor_only_matches_the_instructor_membership_type(): void
    {
        static::assertTrue($this->row(membershipType: 'zeil-instructeur - 2026')->isSailInstructor());
        static::assertFalse($this->row(membershipType: 'Volwassenlid')->isSailInstructor());
        static::assertFalse($this->row(membershipType: 'Erelid')->isSailInstructor());
        static::assertFalse($this->row(membershipType: null)->isSailInstructor());
    }

    public function test_has_membership_type_distinguishes_a_null_type(): void
    {
        static::assertTrue($this->row(membershipType: 'Volwassenlid')->hasMembershipType());
        static::assertFalse($this->row(membershipType: null)->hasMembershipType());
    }

    public function test_address_key_is_case_insensitive_for_street_number_and_addition(): void
    {
        $first = $this->row(
            addressStreet: 'Krachtveld',
            addressHousenumber: '30',
            addressHousenumberAddition: 'A',
            addressPostalcode: '1359 KJ',
        );
        $second = $this->row(
            addressStreet: 'krachtveld',
            addressHousenumber: '30',
            addressHousenumberAddition: 'a',
            addressPostalcode: '1359 KJ',
        );

        static::assertSame($first->addressKey(), $second->addressKey());
        static::assertSame('krachtveld|30|a|1359 KJ', $first->addressKey());
    }

    public function test_address_key_treats_a_missing_addition_as_empty(): void
    {
        static::assertSame('krachtveld|30||1359 KJ', $this->row(addressHousenumberAddition: null)->addressKey());
    }

    private function row(
        string $addressStreet = 'Krachtveld',
        string $addressHousenumber = '30',
        ?string $addressHousenumberAddition = 'A',
        string $addressPostalcode = '1359 KJ',
        ?string $membershipType = 'Volwassenlid',
        ?DateTimeImmutable $stopDate = null,
    ): MappedMemberRow {
        return new MappedMemberRow(
            lidnr: '124277',
            firstName: 'Djoeke',
            infixName: '',
            lastName: 'Adriaanse',
            fullName: 'Djoeke Adriaanse',
            email: 'djoeke@example.com',
            contactEmail: 'djoeke@example.com',
            birthdate: new DateTimeImmutable('2009-02-20'),
            gender: Gender::Female,
            addressStreet: $addressStreet,
            addressHousenumber: $addressHousenumber,
            addressHousenumberAddition: $addressHousenumberAddition,
            addressPostalcode: $addressPostalcode,
            addressCity: 'Almere',
            membershipType: $membershipType,
            membershipName: 'Windsurfer',
            isVolunteer: false,
            startDate: new DateTimeImmutable('2024-04-24'),
            stopDate: $stopDate,
            iban: 'NL75ABNA0510419224',
            bic: 'ABNANL2A',
            accountHolderName: 'Djoeke Adriaanse',
        );
    }
}
