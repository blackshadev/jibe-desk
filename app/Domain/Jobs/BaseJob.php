<?php

declare(strict_types=1);

namespace App\Domain\Jobs;

/** @phpstan-ignore  domain.dependency */
use Illuminate\Bus\Batchable;
/** @phpstan-ignore  domain.dependency */
use Illuminate\Bus\Queueable;
/** @phpstan-ignore  domain.dependency */
use Illuminate\Foundation\Bus\Dispatchable;
/** @phpstan-ignore  domain.dependency */
use Illuminate\Queue\InteractsWithQueue;
/** @phpstan-ignore  domain.dependency */
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
