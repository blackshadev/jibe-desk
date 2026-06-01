<?php

declare(strict_types=1);

namespace App\Domain\Invoices;

final readonly class InvoiceWithLineIds
{
    /**
     * @param InvoiceId $invoiceId
     * @param InvoiceLineId[] $lineIds
     */
    public function __construct(public InvoiceId $invoiceId, public array $lineIds)
    {
    }
}
