<?php

declare(strict_types=1);

namespace App\Domain\Invoices;

use DateTimeInterface;

final readonly class SepaExportInvoice
{
    public function __construct(
        public InvoiceId $invoiceId,
        public string $invoiceNumber,
        public string $recipientName,
        public CompoundPrice $total,
        public string $iban,
        public string $bic,
        public MandateId $mandateId,
        public DateTimeInterface $mandateDate,
    ) {}

    public function amountInCents(): int
    {
        return (int) round($this->total->price * 100);
    }
}
