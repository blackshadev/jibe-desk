<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Registration;

use App\Domain\Registration\MembershipData;
use Tests\UnitTestCase;

final class MembershipDataTest extends UnitTestCase
{
    public function test_create_default_returns_all_false_and_empty(): void
    {
        $data = MembershipData::createDefault();

        static::assertFalse($data->regularWindsurfingLessons);
        static::assertFalse($data->rtc);
        static::assertFalse($data->clubhouseAccess);
        static::assertFalse($data->boardStorage);
        static::assertSame('', $data->watersportFederationNumber);
    }

    public function test_create_from_array_hydrates_all_fields(): void
    {
        $data = MembershipData::createFromArray([
            'regularWindsurfingLessons' => true,
            'rtc' => true,
            'clubhouseAccess' => true,
            'boardStorage' => true,
            'watersportFederationNumber' => '12345',
        ]);

        static::assertTrue($data->regularWindsurfingLessons);
        static::assertTrue($data->rtc);
        static::assertTrue($data->clubhouseAccess);
        static::assertTrue($data->boardStorage);
        static::assertSame('12345', $data->watersportFederationNumber);
    }

    public function test_create_from_array_uses_defaults_for_missing_keys(): void
    {
        $data = MembershipData::createFromArray([]);

        static::assertFalse($data->regularWindsurfingLessons);
        static::assertFalse($data->rtc);
        static::assertFalse($data->clubhouseAccess);
        static::assertFalse($data->boardStorage);
        static::assertSame('', $data->watersportFederationNumber);
    }

    public function test_to_array_returns_expected_structure(): void
    {
        $data = MembershipData::createFromArray([
            'regularWindsurfingLessons' => true,
            'rtc' => false,
            'clubhouseAccess' => true,
            'boardStorage' => false,
            'watersportFederationNumber' => '67890',
        ]);

        static::assertSame(
            [
                'regularWindsurfingLessons' => true,
                'rtc' => false,
                'clubhouseAccess' => true,
                'boardStorage' => false,
                'watersportFederationNumber' => '67890',
            ],
            $data->toArray(),
        );
    }

    public function test_to_and_from_array_roundtrip(): void
    {
        $original = MembershipData::createFromArray([
            'regularWindsurfingLessons' => true,
            'rtc' => true,
            'clubhouseAccess' => false,
            'boardStorage' => true,
            'watersportFederationNumber' => '99999',
        ]);

        $restored = MembershipData::createFromArray($original->toArray());

        static::assertSame($original->toArray(), $restored->toArray());
    }
}
