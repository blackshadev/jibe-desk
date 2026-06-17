<?php

declare(strict_types=1);

namespace App\Domain\Jobs;

use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface JobDispatcher
{
    public function dispatch(Job $job): void;

    /** @param list<Job> $jobs */
    public function batch(string $name, array $jobs): void;
}
