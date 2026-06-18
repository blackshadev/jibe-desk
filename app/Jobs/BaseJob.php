<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Jobs\Job;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

abstract class BaseJob implements Job
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use Batchable;
    use SerializesModels;

    public int $tries = 3;
}
