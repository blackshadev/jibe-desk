<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Jobs;

use App\Domain\Jobs\Job;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

final class DummyTestJob implements Job
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function __construct(
        public string $value,
    ) {}
}
