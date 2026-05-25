<?php

declare(strict_types=1);

namespace App\Domain\Invoices;

use App\Domain\Members\MemberId;
use DateTimeInterface;

final readonly class GenerateInvoice
{
    public function __construct(
        public MemberId $memberId,
        public DateTimeInterface $invoiceDate,
        public ?InvoiceBatchId $invoiceBatchId,
    ) {
    }
}
