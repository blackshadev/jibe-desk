<?php

declare(strict_types=1);

namespace App\Domain\Jobs;

final class JobBatch
{
    /** @param Job[] $jobs */
    public function __construct(
        public string $name,
        public array $jobs,
    ) {}

    public function after(self $jobs): JobChain
    {
        return new JobChain([$this, $jobs]);
    }
}
