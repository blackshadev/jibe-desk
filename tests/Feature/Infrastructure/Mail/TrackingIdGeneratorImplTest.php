<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Mail;

use App\Domain\Mail\TrackingId;
use App\Infrastructure\Mail\TrackingIdGeneratorImpl;
use Tests\FeatureTestCase;

final class TrackingIdGeneratorImplTest extends FeatureTestCase
{
    private TrackingIdGeneratorImpl $generator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->generator = new TrackingIdGeneratorImpl();
    }

    public function test_generate_returns_tracking_id(): void
    {
        $trackingId = $this->generator->generate();

        static::assertInstanceOf(TrackingId::class, $trackingId);
    }

    public function test_generate_returns_valid_uuid(): void
    {
        $trackingId = $this->generator->generate();

        static::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $trackingId->value,
        );
    }

    public function test_generate_returns_unique_ids(): void
    {
        $id1 = $this->generator->generate();
        $id2 = $this->generator->generate();

        static::assertNotSame($id1->value, $id2->value);
    }
}
