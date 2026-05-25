<?php

declare(strict_types=1);

namespace App\Domain\Invoices;

use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface InvoiceGenerator
{
    public function generate(GenerateInvoice $createInvoice): void;
}
