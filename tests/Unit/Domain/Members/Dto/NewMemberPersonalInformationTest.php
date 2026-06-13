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

        static::assertSame('Jan', $dto->firstName);
        static::assertSame('de', $dto->infixName);
        static::assertSame('Vries', $dto->lastName);
        static::assertSame(Gender::Male, $dto->gender);
        static::assertSame($birthdate, $dto->birthdate);
        static::assertSame('jan@example.com', $dto->email);
        static::assertSame('Surfstrand', $dto->addressStreet);
        static::assertSame('2', $dto->addressHousenumber);
        static::assertSame('A', $dto->addressHousenumberAddition);
        static::assertSame('1324CT', $dto->addressPostalcode);
        static::assertSame('Almere', $dto->addressCity);
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

        static::assertSame('', $dto->infixName);
    }
}
