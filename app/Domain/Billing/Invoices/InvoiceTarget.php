<?php

declare(strict_types=1);

namespace App\Domain\Billing\Invoices;

use App\Domain\Members\MemberId;
use DateTimeInterface;
use App\Domain\Billing\Payment\SepaBatchId;

final readonly class InvoiceTarget
{
    public function __construct(
        public MemberId $memberId,
        public DateTimeInterface $invoiceDate,
        public ?SepaBatchId $batchId = null,
    ) {}
}
