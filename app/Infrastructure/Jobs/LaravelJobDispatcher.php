<?php

declare(strict_types=1);

namespace App\Infrastructure\Jobs;

use App\Domain\Jobs\Job;
use App\Domain\Jobs\JobBatch;
use App\Domain\Jobs\JobChain;
use App\Domain\Jobs\JobDispatcher;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;

final class LaravelJobDispatcher implements JobDispatcher
{
    public function dispatch(Job|JobBatch|JobChain $job): void
    {
        if ($job instanceof JobBatch) {
            self::createBatch($job)->dispatch();
            return;
        }

        if ($job instanceof JobChain) {
            Bus::chain(array_map(self::createBatchOrJob(...), $job->jobs))->dispatch();
            return;
        }

        Bus::dispatch($job);
    }

    private static function createBatchOrJob(Job|JobBatch $job): PendingBatch|Job
    {
        return $job instanceof JobBatch ? self::createBatch($job) : $job;
    }

    private static function createBatch(JobBatch $job): PendingBatch
    {
        return Bus::batch($job->jobs)->name($job->name);
    }
}
