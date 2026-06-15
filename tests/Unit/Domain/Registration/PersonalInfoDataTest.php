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

        static::assertSame('', $data->firstName);
        static::assertSame('', $data->infixName);
        static::assertSame('', $data->lastName);
        static::assertSame('', $data->email);
        static::assertSame(Gender::NotSpecified, $data->gender);
        static::assertSame('2000-01-01', $data->birthdate->format('Y-m-d'));
        static::assertSame('', $data->addressStreet);
        static::assertSame('', $data->addressHousenumber);
        static::assertSame('', $data->addressHousenumberAddition);
        static::assertSame('', $data->addressPostalcode);
        static::assertSame('', $data->addressCity);
    }

    public function test_create_from_array_hydrates_all_fields(): void
    {
        $data = PersonalInfoData::createFromArray(self::DEFAULT_DATA);

        static::assertSame('Jan', $data->firstName);
        static::assertSame('de', $data->infixName);
        static::assertSame('Vries', $data->lastName);
        static::assertSame('jan@example.com', $data->email);
        static::assertSame(Gender::Male, $data->gender);
        static::assertSame('1990-01-15', $data->birthdate->format('Y-m-d'));
        static::assertSame('Surfstrand', $data->addressStreet);
        static::assertSame('2', $data->addressHousenumber);
        static::assertSame('A', $data->addressHousenumberAddition);
        static::assertSame('1324CT', $data->addressPostalcode);
        static::assertSame('Almere', $data->addressCity);
    }

    public function test_to_array_returns_expected_structure(): void
    {
        $data = PersonalInfoData::createFromArray(self::DEFAULT_DATA);

        static::assertSame(self::DEFAULT_DATA, $data->toArray());
    }

    public function test_to_and_from_array_roundtrip(): void
    {
        $original = PersonalInfoData::createFromArray(self::DEFAULT_DATA);
        $data = PersonalInfoData::createFromArray($original->toArray());

        static::assertSame($original->toArray(), $data->toArray());
    }
}
