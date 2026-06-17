<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Mail;

use App\Domain\Mail\TrackingId;
use InvalidArgumentException;
use Tests\UnitTestCase;

final class TrackingIdTest extends UnitTestCase
{
    public function test_it_creates_from_valid_uuid(): void
    {
        $trackingId = TrackingId::fromString('550e8400-e29b-41d4-a716-446655440000');

        static::assertSame('550e8400-e29b-41d4-a716-446655440000', $trackingId->value);
    }

    public function test_it_throws_on_invalid_uuid(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TrackingId::fromString('not-a-uuid');
    }

    public function test_it_generates_a_valid_uuid(): void
    {
        $trackingId = TrackingId::generate();

        static::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $trackingId->value,
        );
    }

    public function test_generated_ids_are_unique(): void
    {
        $id1 = TrackingId::generate();
        $id2 = TrackingId::generate();

        static::assertNotSame($id1->value, $id2->value);
    }
}
