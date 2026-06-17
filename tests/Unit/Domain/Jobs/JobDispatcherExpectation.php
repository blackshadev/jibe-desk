<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Jobs;

use App\Domain\Jobs\Job;
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

    /** @param list<Job> $jobs */
    public function expectsBatch(string $name, array $jobs): void
    {
        $this->mock
            ->expects('batch')
            ->with(equalTo($name), equalTo($jobs));
    }

    public function expectsNoBatch(): void
    {
        $this->mock
            ->expects('batch')
            ->never();
    }
}
