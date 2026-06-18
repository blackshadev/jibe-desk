<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Jobs;

use App\Domain\Jobs\Job;
use App\Domain\Jobs\JobBatch;
use App\Domain\Jobs\JobChain;
use Tests\UnitTestCase;

final class JobBatchTest extends UnitTestCase
{
    public function test_it_stores_name_and_jobs(): void
    {
        $job = $this->createMock(Job::class);
        $batch = new JobBatch('send-emails', [$job]);

        static::assertSame('send-emails', $batch->name);
        static::assertSame([$job], $batch->jobs);
    }

    public function test_it_creates_a_job_chain_via_after(): void
    {
        $job = $this->createMock(Job::class);
        $batch1 = new JobBatch('first', [$job]);
        $batch2 = new JobBatch('second', [$job]);

        $chain = $batch1->after($batch2);

        static::assertInstanceOf(JobChain::class, $chain);
        static::assertCount(2, $chain->jobs);
        static::assertSame($batch1, $chain->jobs[0]);
        static::assertSame($batch2, $chain->jobs[1]);
    }

    public function test_it_supports_empty_jobs_array(): void
    {
        $batch = new JobBatch('empty', []);

        static::assertSame('empty', $batch->name);
        static::assertSame([], $batch->jobs);
    }
}
