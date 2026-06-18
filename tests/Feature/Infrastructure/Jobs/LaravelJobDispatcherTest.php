<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Jobs;

use App\Domain\Jobs\JobBatch;
use App\Domain\Jobs\JobChain;
use App\Infrastructure\Jobs\LaravelJobDispatcher;
use Illuminate\Bus\PendingBatch;
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

    public function test_dispatch_dispatches_batch_of_jobs(): void
    {
        Bus::fake();

        $jobs = [
            new DummyTestJob('job-1'),
            new DummyTestJob('job-2'),
            new DummyTestJob('job-3'),
        ];
        $batch = new JobBatch(
            'test-batch',
            $jobs
        );

        $this->dispatcher->dispatch($batch);

        Bus::assertBatched($jobs);
        Bus::assertBatched(static fn (PendingBatch $batch): bool => $batch->name === 'test-batch');
    }

    public function test_dispatch_chains(): void
    {
        Bus::fake();

        $job1 = new DummyTestJob('job-1');
        $job2 = new DummyTestJob('job-2');
        $job3 = new DummyTestJob('job-3');
        $job4 = new DummyTestJob('job-4');

        $chain = new JobChain(
            [
                $job1,
                new JobBatch('batch', [
                    $job2,
                    $job3,
                ]),
                $job4,
            ]
        );

        $this->dispatcher->dispatch($chain);

        Bus::assertChained([
            DummyTestJob::class,
            Bus::chainedBatch(static fn (PendingBatch $batch): bool => $batch->name === 'batch'),
            DummyTestJob::class,
        ]);
    }
}
