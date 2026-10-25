<?php

declare(strict_types=1);

namespace App\Domain\Billing\Invoices;

use App\Domain\Mail\Recipient;
use App\Domain\SharedKernel\CompoundPrice;
use DateTimeInterface;

final readonly class InvoicePaymentDeclinedMailData
{
    public function __construct(
        public InvoiceId $invoiceId,
        public string $invoiceNumber,
        public Recipient $recipient,
        public DateTimeInterface $invoiceDate,
        public CompoundPrice $total,
        public bool $mandateNeedsReauthorisation,
    ) {}
}
