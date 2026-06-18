<?php

declare(strict_types=1);

namespace App\Domain\Jobs;

use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface JobDispatcher
{
    public function dispatch(Job|JobBatch|JobChain $job): void;
}
