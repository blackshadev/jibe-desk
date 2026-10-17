<?php

declare(strict_types=1);

namespace App\Domain\Invoices\Events;

use App\Domain\Invoices\SepaBatchId;

final readonly class SepaBatchClosed
{
    public function __construct(
        public SepaBatchId $batchId,
    ) {}
}
