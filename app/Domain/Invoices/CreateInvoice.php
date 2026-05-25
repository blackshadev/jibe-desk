<?php

declare(strict_types=1);

namespace App\Domain\Invoices;

use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface CreateInvoice
{
    public function create(NewInvoice $invoice): InvoiceId;
}
