<?php

declare(strict_types=1);

namespace App\Domain\Jobs;

final class JobChain
{
    /** @param list<JobBatch|Job> $jobs */
    public function __construct(
        public array $jobs,
    ) {}

    public function after(JobBatch|Job $after): self
    {
        return new JobChain(
            [...$this->jobs, $after],
        );
    }
}
