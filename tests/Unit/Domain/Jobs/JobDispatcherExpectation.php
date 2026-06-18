<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Jobs;

use App\Domain\Jobs\Job;
use App\Domain\Jobs\JobBatch;
use App\Domain\Jobs\JobChain;
use App\Domain\Jobs\JobDispatcher;
use Mockery;
use Mockery\MockInterface;

use function PHPUnit\Framework\equalTo;

final readonly class JobDispatcherExpectation
{
    private function __construct(
        public MockInterface&JobDispatcher $mock,
    ) {}

    public static function create(): self
    {
        return new self(Mockery::mock(JobDispatcher::class));
    }

    public function expectsDispatch(Job|JobBatch|JobChain $arg): void
    {
        $this->mock
            ->expects('dispatch')
            ->with(equalTo($arg));
    }

    public function expectsNoDispatch(): void
    {
        $this->mock
            ->expects('dispatch')
            ->never();
    }
}
