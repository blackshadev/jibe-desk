<?php

declare(strict_types=1);

namespace App\Domain\Invoices;

use JeroenG\Autowire\Attribute\Autowire;

#[Autowire]
interface InvoiceBatchGenerator
{
    public function generate(InvoiceBatch $invoiceBatch): void;
}
