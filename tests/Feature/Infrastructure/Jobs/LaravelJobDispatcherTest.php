<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Jobs;

use App\Domain\Jobs\Job;
use App\Infrastructure\Jobs\LaravelJobDispatcher;
use Illuminate\Bus\PendingBatch;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Bus;
use Tests\FeatureTestCase;

final class LaravelJobDispatcherTest extends FeatureTestCase
{
    private LaravelJobDispatcher $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dispatcher = new LaravelJobDispatcher();
    }

    public function test_dispatch_sends_job_to_bus(): void
    {
        Bus::fake();

        $job = new DummyTestJob('test-value');

        $this->dispatcher->dispatch($job);

        Bus::assertDispatched(DummyTestJob::class);
    }

    public function test_batch_dispatches_batch_of_jobs(): void
    {
        Bus::fake();

        $jobs = [
            new DummyTestJob('job-1'),
            new DummyTestJob('job-2'),
            new DummyTestJob('job-3'),
        ];

        $this->dispatcher->batch('test-batch', $jobs);

        Bus::assertBatched($jobs);
    }

    public function test_batch_applies_name(): void
    {
        Bus::fake();

        $this->dispatcher->batch('my-custom-batch', [
            new DummyTestJob('job-1'),
        ]);

        Bus::assertBatched(static fn (PendingBatch $batch): bool => $batch->name === 'my-custom-batch');
    }
}

final class DummyTestJob implements Job
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function __construct(
        public string $value,
    ) {}
}
