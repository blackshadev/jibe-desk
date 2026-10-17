<?php

declare(strict_types=1);

namespace App\Domain\Billing\Payment;

use App\Domain\SharedKernel\CompoundPrice;
use DateTimeInterface;

final readonly class SepaBatchEmailData
{
    public function __construct(
        public SepaBatchId $id,
        public DateTimeInterface $invoiceDate,
        public int $invoiceCount,
        public CompoundPrice $total,
    ) {}
}
