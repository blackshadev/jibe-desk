<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Jobs;

use App\Domain\Jobs\Job;
use App\Domain\Jobs\JobBatch;
use App\Domain\Jobs\JobChain;
use Tests\UnitTestCase;

final class JobChainTest extends UnitTestCase
{
    public function test_it_stores_jobs(): void
    {
        $job = $this->createMock(Job::class);
        $chain = new JobChain([$job]);

        static::assertSame([$job], $chain->jobs);
    }

    public function test_it_appends_a_job_batch_via_after(): void
    {
        $job = $this->createMock(Job::class);
        $batch = new JobBatch('batch', [$job]);
        $chain = new JobChain([$job]);

        $newChain = $chain->after($batch);

        static::assertInstanceOf(JobChain::class, $newChain);
        static::assertCount(2, $newChain->jobs);
        static::assertSame($job, $newChain->jobs[0]);
        static::assertSame($batch, $newChain->jobs[1]);
    }

    public function test_it_appends_a_job_via_after(): void
    {
        $job1 = $this->createMock(Job::class);
        $job2 = $this->createMock(Job::class);
        $chain = new JobChain([$job1]);

        $newChain = $chain->after($job2);

        static::assertCount(2, $newChain->jobs);
        static::assertSame($job1, $newChain->jobs[0]);
        static::assertSame($job2, $newChain->jobs[1]);
    }

    public function test_it_does_not_modify_the_original_chain(): void
    {
        $job = $this->createMock(Job::class);
        $batch = new JobBatch('batch', [$job]);
        $chain = new JobChain([$job]);

        $chain->after($batch);

        static::assertCount(1, $chain->jobs);
    }

    public function test_it_chains_multiple_items(): void
    {
        $job = $this->createMock(Job::class);
        $batch1 = new JobBatch('first', [$job]);
        $batch2 = new JobBatch('second', [$job]);

        $chain = (new JobChain([$batch1]))->after($batch2)->after($job);

        static::assertCount(3, $chain->jobs);
        static::assertSame($batch1, $chain->jobs[0]);
        static::assertSame($batch2, $chain->jobs[1]);
        static::assertSame($job, $chain->jobs[2]);
    }
}
