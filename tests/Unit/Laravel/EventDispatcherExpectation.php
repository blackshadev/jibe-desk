<?php

declare(strict_types=1);

namespace Tests\Unit\Laravel;

use App\Domain\Members\Events\NewMemberRegistration;
use Illuminate\Contracts\Events\Dispatcher;
use Mockery;
use Mockery\MockInterface;

use function PHPUnit\Framework\equalTo;

final readonly class EventDispatcherExpectation
{
    private function __construct(
        public MockInterface&Dispatcher $mock,
    ) {}

    public static function create(): self
    {
        return new self(Mockery::mock(Dispatcher::class));
    }

    public function expectsDispatch(mixed $expectedEvent): void
    {
        $this->mock
            ->expects('dispatch')
            ->with(equalTo($expectedEvent))
            ->andReturnNull();
    }

    public function expectsDispatchWith(NewMemberRegistration $registration): void
    {
        $this->mock
            ->expects('dispatch')
            ->with(equalTo($registration))
            ->andReturnNull();
    }

    public function expectsNotToDispatch(): void
    {
        $this->mock
            ->expects('dispatch')
            ->never();
    }
}
