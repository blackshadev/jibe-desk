<?php

declare(strict_types=1);

namespace App\Domain\Invoices;

use DateTimeInterface;

final readonly class InvoiceBatchEmailData
{
    public function __construct(
        public InvoiceBatchId $id,
        public DateTimeInterface $invoiceDate,
        public int $invoiceCount,
        public CompoundPrice $total,
    ) {}
}
