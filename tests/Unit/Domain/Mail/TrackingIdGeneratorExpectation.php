<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Mail;

use App\Domain\Mail\TrackingId;
use App\Domain\Mail\TrackingIdGenerator;
use Mockery;
use Mockery\MockInterface;

final readonly class TrackingIdGeneratorExpectation
{
    private function __construct(
        public MockInterface&TrackingIdGenerator $mock,
    ) {}

    public static function create(): self
    {
        return new self(Mockery::mock(TrackingIdGenerator::class));
    }

    public function expectsGenerate(TrackingId $trackingId): void
    {
        $this->mock
            ->expects('generate')
            ->withNoArgs()
            ->andReturn($trackingId);
    }
}
