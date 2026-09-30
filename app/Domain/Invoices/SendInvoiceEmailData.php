<?php

declare(strict_types=1);

namespace App\Domain\Invoices;

final readonly class SendInvoiceEmailData
{
    public function __construct(
        public InvoiceId $invoiceId,
        public bool $isResend = false,
    ) {}
}
