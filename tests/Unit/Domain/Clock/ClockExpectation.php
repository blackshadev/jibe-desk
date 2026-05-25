<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Clock;

use DateTimeInterface;
use Mockery;
use Mockery\MockInterface;
use Psr\Clock\ClockInterface;

final readonly class ClockExpectation
{
    private function __construct(public MockInterface&ClockInterface $mock)
    {
    }

    public static function create(): self
    {
        return new self(Mockery::mock(ClockInterface::class));
    }

    public function expectsNow(DateTimeInterface $dateTime): void
    {
        $this->mock
            ->expects('now')
            ->withNoArgs()
            ->andReturn($dateTime);
    }
}
