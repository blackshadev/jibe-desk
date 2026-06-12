<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Registration;

use App\Domain\Members\Gender;
use App\Domain\Registration\PersonalInfoData;
use Tests\UnitTestCase;

final class PersonalInfoDataTest extends UnitTestCase
{
    private const DEFAULT_DATA = [
        'firstName' => 'Jan',
        'infixName' => 'de',
        'lastName' => 'Vries',
        'email' => 'jan@example.com',
        'gender' => 'M',
        'birthdate' => '1990-01-15',
        'addressStreet' => 'Surfstrand',
        'addressHousenumber' => '2',
        'addressHousenumberAddition' => 'A',
        'addressPostalcode' => '1324CT',
        'addressCity' => 'Almere',
    ];

    public function test_create_default_returns_empty_values(): void
    {
        $data = PersonalInfoData::createDefault();

        self::assertSame('', $data->firstName);
        self::assertSame('', $data->infixName);
        self::assertSame('', $data->lastName);
        self::assertSame('', $data->email);
        self::assertSame(Gender::Unknown, $data->gender);
        self::assertSame('2000-01-01', $data->birthdate->format('Y-m-d'));
        self::assertSame('', $data->addressStreet);
        self::assertSame('', $data->addressHousenumber);
        self::assertSame('', $data->addressHousenumberAddition);
        self::assertSame('', $data->addressPostalcode);
        self::assertSame('', $data->addressCity);
    }

    public function test_create_from_array_hydrates_all_fields(): void
    {
        $data = PersonalInfoData::createFromArray(self::DEFAULT_DATA);

        self::assertSame('Jan', $data->firstName);
        self::assertSame('de', $data->infixName);
        self::assertSame('Vries', $data->lastName);
        self::assertSame('jan@example.com', $data->email);
        self::assertSame(Gender::Male, $data->gender);
        self::assertSame('1990-01-15', $data->birthdate->format('Y-m-d'));
        self::assertSame('Surfstrand', $data->addressStreet);
        self::assertSame('2', $data->addressHousenumber);
        self::assertSame('A', $data->addressHousenumberAddition);
        self::assertSame('1324CT', $data->addressPostalcode);
        self::assertSame('Almere', $data->addressCity);
    }

    public function test_to_array_returns_expected_structure(): void
    {
        $data = PersonalInfoData::createFromArray(self::DEFAULT_DATA);

        self::assertSame(self::DEFAULT_DATA, $data->toArray());
    }

    public function test_to_and_from_array_roundtrip(): void
    {
        $original = PersonalInfoData::createFromArray(self::DEFAULT_DATA);
        $data = PersonalInfoData::createFromArray($original->toArray());

        self::assertSame($original->toArray(), $data->toArray());
    }
}
