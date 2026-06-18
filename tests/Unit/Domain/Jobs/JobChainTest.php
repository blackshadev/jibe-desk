<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Jobs;

use App\Domain\Jobs\JobBatch;
use App\Domain\Jobs\JobChain;
use Tests\Feature\Infrastructure\Jobs\DummyTestJob;
use Tests\UnitTestCase;

final class JobChainTest extends UnitTestCase
{
    public function test_it_stores_jobs(): void
    {
        $jobs = [new DummyTestJob('')];
        $chain = new JobChain($jobs);

        static::assertSame($jobs, $chain->jobs);
    }

    public function test_it_appends_a_job_batch_via_after(): void
    {
        $jobsChain = [new DummyTestJob('chain')];
        $jobsBatch = [new DummyTestJob('batch')];

        $batch = new JobBatch('batch', $jobsBatch);
        $chain = new JobChain($jobsChain);

        $newChain = $chain->after($batch);

        static::assertCount(2, $newChain->jobs);
        static::assertSame(
            [
                $jobsChain[0],
                $batch,
            ],
            $newChain->jobs,
        );
    }

    public function test_it_appends_a_job_via_after(): void
    {
        $job1 = new DummyTestJob('1');
        $job2 = new DummyTestJob('2');
        $chain = new JobChain([$job1]);

        $newChain = $chain->after($job2);

        static::assertCount(2, $newChain->jobs);
        static::assertSame($job1, $newChain->jobs[0]);
        static::assertSame($job2, $newChain->jobs[1]);
    }

    public function test_it_does_not_modify_the_original_chain(): void
    {
        $job = new DummyTestJob('1');
        $batch = new JobBatch('batch', [$job]);
        $chain = new JobChain([$job]);

        $chain->after($batch);

        static::assertCount(1, $chain->jobs);
    }

    public function test_it_chains_multiple_items(): void
    {
        $job = new DummyTestJob('1');
        $batch1 = new JobBatch('first', [$job]);
        $batch2 = new JobBatch('second', [$job]);

        $chain = new JobChain([$batch1])
            ->after($batch2)
            ->after($job);

        static::assertCount(3, $chain->jobs);
        static::assertSame($batch1, $chain->jobs[0]);
        static::assertSame($batch2, $chain->jobs[1]);
        static::assertSame($job, $chain->jobs[2]);
    }
}
