<?php

declare(strict_types=1);

namespace App\Domain\Billing\Payment\Events;

use App\Domain\Billing\Payment\SepaBatchId;

final readonly class SepaBatchClosed
{
    public function __construct(
        public SepaBatchId $batchId,
    ) {}
}
