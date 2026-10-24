<?php

declare(strict_types=1);

namespace App\Domain\Billing\Invoices\Events;

use App\Domain\Billing\Invoices\InvoiceId;

final readonly class InvoicePaymentDeclined
{
    public function __construct(
        public InvoiceId $invoiceId,
    ) {}
}
