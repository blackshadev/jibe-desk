<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Members\Dto;

use App\Domain\Members\Dto\NewMemberPersonalInformation;
use App\Domain\Members\Gender;
use DateTimeImmutable;
use Tests\UnitTestCase;

final class NewMemberPersonalInformationTest extends UnitTestCase
{
    public function test_constructor_stores_all_properties(): void
    {
        $birthdate = new DateTimeImmutable('1990-01-15');

        $dto = new NewMemberPersonalInformation(
            firstName: 'Jan',
            infixName: 'de',
            lastName: 'Vries',
            gender: Gender::Male,
            birthdate: $birthdate,
            email: 'jan@example.com',
            addressStreet: 'Surfstrand',
            addressHousenumber: '2',
            addressHousenumberAddition: 'A',
            addressPostalcode: '1324CT',
            addressCity: 'Almere',
        );

        self::assertSame('Jan', $dto->firstName);
        self::assertSame('de', $dto->infixName);
        self::assertSame('Vries', $dto->lastName);
        self::assertSame(Gender::Male, $dto->gender);
        self::assertSame($birthdate, $dto->birthdate);
        self::assertSame('jan@example.com', $dto->email);
        self::assertSame('Surfstrand', $dto->addressStreet);
        self::assertSame('2', $dto->addressHousenumber);
        self::assertSame('A', $dto->addressHousenumberAddition);
        self::assertSame('1324CT', $dto->addressPostalcode);
        self::assertSame('Almere', $dto->addressCity);
    }

    public function test_infix_name_can_be_empty(): void
    {
        $dto = new NewMemberPersonalInformation(
            firstName: 'Jan',
            infixName: '',
            lastName: 'Vries',
            gender: Gender::Male,
            birthdate: new DateTimeImmutable('1990-01-15'),
            email: 'jan@example.com',
            addressStreet: 'Surfstrand',
            addressHousenumber: '2',
            addressHousenumberAddition: '',
            addressPostalcode: '1324CT',
            addressCity: 'Almere',
        );

        self::assertSame('', $dto->infixName);
    }
}
