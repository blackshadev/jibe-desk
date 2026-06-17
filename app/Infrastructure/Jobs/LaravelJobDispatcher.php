<?php

declare(strict_types=1);

namespace App\Infrastructure\Jobs;

use App\Domain\Jobs\JobDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Bus;

final class LaravelJobDispatcher implements JobDispatcher
{
    public function dispatch(ShouldQueue $job): void
    {
        Bus::dispatch($job);
    }

    public function batch(string $name, array $jobs): void
    {
        Bus::batch($jobs)
            ->name($name)
            ->dispatch();
    }
}
