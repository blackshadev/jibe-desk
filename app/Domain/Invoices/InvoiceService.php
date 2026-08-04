<?php

declare(strict_types=1);

namespace App\Domain\Invoices;

use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface InvoiceService
{
    public function createCredit(InvoiceId $originalInvoiceId): InvoiceId;

    public function markAsPaid(InvoiceIdList $ids): void;

    public function markAsDeclined(InvoiceIdList $ids): void;

    public function markAsPending(InvoiceIdList $ids): void;
}
