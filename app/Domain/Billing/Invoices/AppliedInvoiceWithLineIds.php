<?php

declare(strict_types=1);

namespace App\Domain\Billing\Invoices;

final readonly class AppliedInvoiceWithLineIds
{
    /**
     * @param InvoiceLineId[] $lineIds
     */
    public function __construct(
        public bool $isNew,
        public InvoiceId $invoiceId,
        public array $lineIds,
    ) {}
}
