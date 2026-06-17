<?php

declare(strict_types=1);

namespace App\Domain\Invoices\Events;

use App\Domain\Invoices\InvoiceBatchId;

final readonly class InvoiceBatchClosed
{
    public function __construct(
        public InvoiceBatchId $batchId,
    ) {}
}
