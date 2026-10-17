<?php

declare(strict_types=1);

namespace App\Domain\Billing\Payment;

use DateTimeInterface;

final readonly class SepaBatch
{
    public function __construct(
        public DateTimeInterface $invoiceDate,
        public DateTimeInterface $sepaTransferDate,
    ) {}
}
