<?php

declare(strict_types=1);

namespace App\Domain\Billing\Payment\Readiness;

use App\Domain\Billing\Invoices\InvoiceId;

final readonly class ExportOverride
{
    public function __construct(
        public InvoiceId $invoiceId,
        public ExportReadinessReason $reason,
        public string $note,
    ) {}
}
