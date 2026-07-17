<?php

declare(strict_types=1);

namespace App\Domain\Invoices;

use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface InvoiceRepository
{
    public function create(NewInvoice $invoice): InvoiceId;

    public function applyLines(ApplyInvoiceLines $invoice): AppliedInvoiceWithLineIds;

    /**
     * Mark an individual invoice as paid by updating its status.
     */
    public function markAsPaid(InvoiceId $id): void;
}
